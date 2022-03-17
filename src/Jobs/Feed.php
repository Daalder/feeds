<?php

namespace Daalder\Feeds\Jobs;

use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Closure;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
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
    use Dispatchable, Queueable;

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
    protected $chunkSize = 500;

    /** @var integer[] */
    protected $excludedGoogleAttributeSets = [720];

    /** @var integer */
    public $timeout = 7200;

    /** @var string[] */
    protected $fieldNames;

    /** @var string */
    protected $type = '';

    /** @var string */
    protected $vendor = 'admarkt';

    /**
     * Feed constructor.
     *
     * @param  Store  $store
     */
    public function __construct(Store $store)
    {
        $this->store = $store;
        $this->feedsBucket = config('feeds.bucket');

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
        $fileName = $this->store->code.'_'.today()->toDateString().'.'.$this->type;
        $localFileName = storage_path().'/feeds/'.$this->vendor.'/'.$fileName;

        $feedHeader = '';

        // Get the header (first row) of the feed
        switch($this->type) {
            case 'txt':
                $feedHeader = $this->convertToTxtLine($this->fieldNames);
                break;
            case 'csv':
                $feedHeader = $this->convertToCsvLine($this->fieldNames);
                break;
        }

        // Write the header (first row) of the feed
        File::put($localFileName, $feedHeader);

        // Query products
        $query = $this->productRepository->newQuery()
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

        // Chunk-process the products
        $query->chunk($this->chunkSize, function($products) {
            $validProducts = $products->filter->isPushable();
        });

        // Upload the file to S3
        $this->putToS3($localFileName, $vendor.'/'.$this->store->code.'.'.$type);
    }

    protected function productChunkHandler($products) {
        $feed = '';

        /* @var $product Product */
        foreach ($products as $product) {
            if (!$product->isPushable()) {
                continue;
            }

            try {
                $fields = $fieldsCallable($product);
            } catch (\Exception $ex) {
                echo "\n".sprintf('Error when exporting product %s for feed. %s %s %s ', $product->id,
                        $ex->getMessage(), $ex->getFile(), $ex->getLine())."\n";
                continue;
            }

            // Product might miss some required attribute for said feed, skip if that's the case
            if (!$fields) {
                continue;
            }

            $feed .= $this->{$method}($fields);
        }

        File::append($localFileName, $feed);
    }

    protected function getCountryCode($store)
    {
        return Str::upper(Str::after($store->default_locale, '_'));
    }

    protected function getCurrency()
    {
        return 'EUR';
    }

    protected function getFormattedPrice(?Price $price)
    {
        if (!$price) {
            return '';
        }

        $currency = optional($price->currency)->code ?? $this->getCurrency();
        $priceAsMoney = $price->priceAsMoney();
        $priceString = $priceAsMoney ? MoneyFactory::toString($priceAsMoney) : '';

        return $priceString.' '.$currency;
    }

    protected function getFormattedListPrice(?Price $price)
    {
        if (!$price || !$price->listPriceAsMoney()) {
            return '';
        }

        // Temporary fix for daalder ~13.15.5
        if ($price->list_price === $price->price) {
            return '';
        }

        $currency = optional($price->currency)->code ?? $this->getCurrency();
        $priceAsMoney = $price->listPriceAsMoney();
        $priceString = $priceAsMoney ? MoneyFactory::toString($priceAsMoney) : '';

        return $priceString.' '.$currency;
    }

    /**
     * @param $source
     * @param $destination
     */
    protected function putToS3($source, $destination)
    {
        $this->s3Client->putObject(
            [
                'Bucket' => $this->feedsBucket,
                'Key' => $destination,
                'SourceFile' => $source,
                'ACL' => 'public-read',
            ]
        );
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
     * @param  \Exception  $exception
     *
     * @return void
     */
    public function failed(Exception $exception): void
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
}
