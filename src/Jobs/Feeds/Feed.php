<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Closure;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Pionect\Daalder\Events\Feed\FeedJobFailed;
use Pionect\Daalder\Models\Price\Price;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Store\Store;
use Pionect\Daalder\Services\ActiveStore;
use Pionect\Daalder\Services\MoneyFactory;

abstract class Feed implements ShouldQueue
{
    use Dispatchable, Queueable, Batchable, InteractsWithQueue;

    /** @var Product */
    protected $productRepository;

    /** @var Store */
    protected $store;

    /** @var S3Client */
    protected $s3Client;

    /** @var string */
    protected $feedsBucket = '';

    /** @var string */
    protected $protocol = 'https://';

    /** @var integer */
    protected $chunkSize = 50;//500;

    /** @var integer[] */
    protected $excludedGoogleAttributeSets = [720];

    /** @var integer */
    public $timeout = 7200;

    /** @var string[] */
    protected $fieldNames;

    /** @var string */
    public $type = '';

    /** @var string */
    public $vendor = 'admarkt';

    /**
     * Feed constructor.
     *
     * @param  Store  $store
     */
    public function __construct(Store $store)
    {
        $this->store = $store;
        $this->feedsBucket = config('daalder-feeds.bucket');

        $this->onQueue('feeds');
    }

    /**
     * @param  Product  $productRepository
     * @param  S3Client|S3ClientInterface  $s3Client
     */
    public function handle(Product $productRepository, S3ClientInterface $s3Client)
    {
        $this->productRepository = $productRepository;
        $this->s3Client = $s3Client;

        resolve(ActiveStore::class)->set($this->store);

        $this->generate();
    }

    abstract protected function productToFeedRow(Product $product);

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getProductQuery() {
        return $this->productRepository->newQuery()
            // that have products
            ->has('images')
            // that are active for $this->store
            ->whereHas('stores', function (Builder $query) {
                $query->where(Store::table().'.id', $this->store->id);
            })
            // that have an attributeset
            ->hasAttributeSet()
            // Include brand and attributeset relationships
            ->with(['brand', 'productattributeset']);
    }

    private function generate() {
        // Create storage/feeds directory if it doesn't exist
        if (!File::exists(storage_path('feeds'))) {
            File::makeDirectory(storage_path('feeds'));
        }

        // Create storage/feeds/{vendor} directory if it doesn't exist
        if (!File::exists(storage_path("feeds/".$this->vendor))) {
            File::makeDirectory(storage_path("feeds/".$this->vendor));
        }

        // Prepare filename and path
        $fileName = $this->store->code.'.'.$this->type;
        $localFilePath = storage_path().'/feeds/'.$this->vendor.'/'.$fileName;

        $feedHeader = $this->formatFeedLine($this->fieldNames);

        // Write the header (first row) of the feed
        File::put($localFilePath, $feedHeader);

        // Query products
        $query = $this->getProductQuery();

        if(!$query) {
            return;
        }

        // Chunk-process the products
        $query->chunk($this->chunkSize, function($products) use ($localFilePath) {
            // Filter products by isPushable
            $validProducts = $products->filter->isPushable();

            // Map the validProducts into feed rows
            $feedLines = $validProducts
                ->map(function($product) use ($localFilePath) {
                    try {
                        // Call the productToFeedRow method on the extending class (AdmarktFeed, BeslistFeed, etc).
                        $feedRow = $this->productToFeedRow($product);

                        // Overwrite preconfigured fields for this vendor
                        $fieldOverwrites = config('daalder-feeds.field-overwrites.'.$this->vendor);
                        if($fieldOverwrites) {
                            foreach($fieldOverwrites as $field => $value) {
                                $feedRow[$field] = $value;
                            }
                        }

                        // Format and return the feed row
                        return $this->formatFeedLine($feedRow);
                    } catch (\Exception $ex) {
                        // Log exception and return an empty string
                        logger()->error("Error when exporting product ".$product->id." for feed. ".$ex->getMessage()." ".$ex->getFile()." ".$ex->getLine()."\n");
                        return '';
                    }
                })
                // Implode the array of rows into a single string
                ->implode('');

            // Append the feed rows for the product chunk to the feed file
            File::append($localFilePath, $feedLines);
        });

        // Upload the file to S3
        $this->uploadToS3($localFilePath);
        $this->removeLocalFile($localFilePath);
    }

    protected function getCountryCode()
    {
        return Str::upper(Str::after($this->store->default_locale, '_'));
    }

    protected function getCurrency(Product $product)
    {
        return optional(optional($product->getCurrentPrice())->currency)->symbol ?? $this->store->currency_code;
    }

    protected function getFormattedPrice(Product $product)
    {
        $price = $product->getCurrentPrice();

        if(!$price || !$price->priceAsMoney()) {
            return '';
        }

        $currency = $this->getCurrency($product);
        $priceAsMoney = $price->priceAsMoney();

        return MoneyFactory::format($priceAsMoney);
    }

    protected function getFormattedListPrice(Product $product)
    {
        $price = $product->getCurrentListPrice();

        if (!$price || !$price->listPriceAsMoney()) {
            return '';
        }

        // TODO: This is a temporary fix for daalder ~13.15.5
        if ($price->list_price === $price->price) {
            return '';
        }

        $currency = $this->getCurrency($product);
        $listPriceAsMoney = $price->listPriceAsMoney();

        return MoneyFactory::format($listPriceAsMoney);
    }

    /**
     * @param string $source
     */
    protected function uploadToS3(string $localFilePath)
    {
        // Prepare path to file on S3
        $targetDirectory = $this->store->code.'/'.$this->vendor;
        $targetFileName = $this->vendor.'.'.$this->type;
        $targetPath = $targetDirectory .'/'. $targetFileName;

        $currentFeed = null;

        try {
            // Get the currently active feed file
            $currentFeed = $this->s3Client->getObject([
                "Bucket" => $this->feedsBucket,
                "Key" => $targetPath,
            ]);
        } catch(\Exception $e) {}

        // If the currently active feed file was found
        if($currentFeed) {
            // Get the formatted date for when the currently active feed file was last modified and prepare a new filename using it
            $lastModifiedDate = $currentFeed->get('LastModified');
            $lastModifiedDate = Carbon::createFromTimestamp($lastModifiedDate->getTimestamp())->toDateString();
            $newNameForOldFeed = $this->vendor.'_'.$lastModifiedDate.'.'.$this->type;

            try {
                // Attempt to get the backup file that's about to be created. This will throw an error if it doesn't
                // exist yet. If no error is thrown, the backup file already exists and we don't overwrite it.
                $this->s3Client->getObject([
                    'Bucket' => $this->feedsBucket,
                    "Key" => $targetDirectory.'/'.$newNameForOldFeed,
                ]);
            } catch(\Exception $e) {
                // Copy the currently active feed file to {vendor}_{datestring}.{extension} as a backup
                $currentFeed = $this->s3Client->copyObject([
                    'Bucket' => $this->feedsBucket,
                    "Key" => $targetDirectory.'/'.$newNameForOldFeed,
                    "CopySource" => $this->feedsBucket.'/'.$targetPath,
                    'MetadataDirective' => 'REPLACE'
                ]);
            }
        }

        // Overwrite the currently active feed file with the newly generated one
        $this->s3Client->putObject(
            [
                'Bucket' => $this->feedsBucket,
                'Key' => $targetPath,
                'SourceFile' => $localFilePath,
                'ACL' => 'public-read',
            ]
        );
    }

    protected function removeLocalFile(string $localFilePath) {
        File::delete($localFilePath);
    }

    protected function formatFeedLine(array $fields) {
        switch($this->type) {
            case 'txt':
                return $this->convertToTxtLine($fields);
                break;
            case 'csv':
                return $this->convertToCsvLine($fields);
                break;
        }
    }

    /** @noinspection PhpUnusedPrivateMethodInspection
     *
     * @param  array  $fields
     *
     * @return string
     */
    protected function convertToTxtLine(array $fields)
    {
        $fields = array_map([$this, 'cleanValue'], $fields);

        $productLine = implode("\t", $fields);
        $productLine .= "\n";

        return $productLine;
    }

    /** @noinspection PhpUnusedPrivateMethodInspection
     *
     * @param  array  $fields
     *
     * @return string
     */
    protected function convertToCsvLine(array $fields)
    {
        $fields = array_map([$this, 'cleanValue'], $fields);

        $handle = fopen('php://temp/maxmemory:1048576', 'w');

        // UTF-8
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, $fields);

        rewind($handle);
        $productLine = stream_get_contents($handle);
        fclose($handle);

        return $productLine;
    }

    /**
     * @param $value
     * @return string
     */
    public function cleanValue($value)
    {
        return trim(strip_tags(str_replace(["\t", "\n", "\r"], ' ', $value)));
    }

    /**
     * The job failed to process.
     *
     * @param  \Exception|\Error  $exception
     *
     * @return void
     */
    public function failed($exception): void
    {
        event(new FeedJobFailed($exception));
    }

    public function getDelivery($product)
    {
        switch ($product->delivery) {
            case '15':
                return '1-2 dagen';
            case '12':
                return '1-3 dagen';
            case '11':
                return '1-5 dagen';
            case '10':
                return '1-8 dagen';
            case '14':
                return '2-4 dagen';
            case '13':
                return '4-6 dagen';
            case '18':
                return '1-2 weken';
            case '17':
                return '1-3 weken';
            case '16':
                return '2-4 weken';
            case '51':
                return 'Niet op voorraad';
            default:
                return 'Onbekend';
        }
    }

    /**
     * @return string
     */
    protected function getHost()
    {
        return $this->protocol.$this->store->domain;
    }

    /**
     * @param $marge
     * @return int
     */
    protected function margeMapper($marge)
    {
        switch ($marge) {
            case $marge <= 0:
                return 0;
            case $marge > 70:
                return 29;
            default:
                return (int) ceil($marge / 2.5);
        }
    }

    /**
     * @param  Product  $product
     * @return int
     */
    protected function getInStock(Product $product)
    {
        return ($product->stock) ? $product->stock->sum('in_stock') : 0;
    }

    /**
     * @return string|null
     */
    protected function getTag(Product $product)
    {
        $tag = $product->tags()->where('name', 'like', 'G:%')->first();

        return ($tag) ? $tag->name : null;
    }

    /**
     * @param  Product  $product
     * @return string|null
     */
    protected function getImageLink(Product $product)
    {
        $image = $product->images()->first();
        return optional($image)->src;
    }

    /**
     * @param  Product  $product
     * @return string
     */
    protected function getCategories(Product $product)
    {
        $path = '';

        $categories = $product->feedCategories()
            ->whereNull('feed_consumer_id')
            ->orderBy('feed_category_product.id')
            ->get();

        if (null !== $categories) {
            /* @var $category \Pionect\Daalder\Models\Feed\Category */
            foreach ($categories as $category) {
                $path .= $category->name.' > ';
            }

            $path = rtrim($path, ' >');
        }

        return $path;
    }
}
