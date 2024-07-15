<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Daalder\Feeds\Events\AfterCreatingFeedProductQuery;
use Daalder\Feeds\Events\AfterCreatingFeedRow;
use Daalder\Feeds\Events\BeforeCreatingRowHeader;
use Daalder\Feeds\Services\FeedPriceFormatter;
use Error;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Visibility as FileVisibility;
use Pionect\Daalder\Events\Feed\FeedJobFailed;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Product\Repositories\ProductRepository;
use Pionect\Daalder\Models\Product\Visibility;
use Pionect\Daalder\Models\Store\Store;
use Pionect\Daalder\Services\ActiveStore;

abstract class Feed implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable, Batchable, InteractsWithQueue;

    /** @var ProductRepository */
    protected $productRepository;

    /** @var Store */
    protected $store;

    protected string $feedsDisk = '';

    /** @var string */
    protected $protocol = 'https://';

    /** @var int */
    protected $chunkSize = 50; //500;

    /** @var int[] */
    public $excludedGoogleAttributeSets = [720];

    /** @var int */
    public $timeout = 7200;

    /** @var string[] */
    protected $fieldNames;

    /** @var string */
    public $type;

    /** @var string */
    public $vendor;

    /** @var string */
    public $filePath;

    /**
     * Feed constructor.
     */
    public function __construct(Store $store)
    {
        $this->store = $store;
        $this->priceFormatter = new FeedPriceFormatter($this->store);
        $this->feedsDisk = config('daalder-feeds.disk');

        $this->onQueue(config('daalder-feeds.feeds-queue'));
    }

    public function handle(ProductRepository $productRepository): void
    {
        if (optional($this->batch())->cancelled()) {
            return;
        }

        $this->productRepository = $productRepository;

        resolve(ActiveStore::class)->set($this->store);

        $this->generate();
    }

    abstract protected function productToFeedRow(Product $product);

    protected function getProductQuery(): Builder
    {
        return $this->productRepository->newQuery()
            ->where('is_for_sale', 1)
            // that have products
            ->has('images')
            // that are active for $this->store
            ->whereHas('stores', function (Builder $query) {
                $query->where(Store::table().'.id', $this->store->id);
            })
            ->whereHas('visibility', function (Builder $query) {
                $query->where(Visibility::table().'.code', '!=', Visibility::ONLY_SUBPRODUCT);
            })
            ->has('prices')
            // that have an attributeset
            ->hasAttributeSet()
            // Include brand and attributeset relationships
            ->with(['brand', 'productattributeset']);
    }

    private function generate(): void
    {
        // Create storage/feeds directory if it doesn't exist
        if (! File::exists(storage_path('feeds'))) {
            File::makeDirectory(storage_path('feeds'));
        }

        // Create storage/feeds/{vendor} directory if it doesn't exist
        if (! File::exists(storage_path('feeds/'.$this->vendor))) {
            File::makeDirectory(storage_path('feeds/'.$this->vendor));
        }

        // Prepare filename and path
        $randomString = bin2hex(random_bytes(5));
        $fileName = $this->store->code.'_'.$randomString.'.'.$this->type;
        $this->filePath = storage_path().'/feeds/'.$this->vendor.'/'.$fileName;

        // Remove local feed file if it exists
        if (File::exists($this->filePath)) {
            $this->removeLocalFile();
        }

        // UTF-8 BOM and header (column names)
        $feedHeader = chr(0xEF).chr(0xBB).chr(0xBF);
        // Before Creating Header Event
        $beforeCreatingRowHeaderEvent = new BeforeCreatingRowHeader($this->vendor, $this->store, $this->fieldNames);
        event($beforeCreatingRowHeaderEvent);

        $this->fieldNames = $beforeCreatingRowHeaderEvent->getFieldNames();
        $feedHeader .= $this->formatFeedLine($this->fieldNames);

        // Write the header (first row) of the feed
        File::put($this->filePath, $feedHeader);

        // Query products
        $query = $this->getProductQuery();

        $event = new AfterCreatingFeedProductQuery(get_class($this), $query);
        event($event);
        $query = $event->getProductsQuery();

        if (! $query) {
            return;
        }

        $expectedProductCount = $this->getProductsCount($query);

        // Chunk-process the products
        $query->chunkById($this->chunkSize, function ($products) {
            $feedLines = collect([]);
            // Map the validProducts into feed rows
            $products
                ->each(function ($product) use ($feedLines) {
                    try {
                        // Call the productToFeedRow method on the extending class (AdmarktFeed, BeslistFeed, etc).
                        $feedRow = $this->productToFeedRow($product);
                        $feedRows = Arr::isAssoc($feedRow) ? [$feedRow] : $feedRow;

                        foreach ($feedRows as $row) {
                            $feedLines->push($this->postProcessFeedRow($row, $product));
                        }
                    } catch (Exception $ex) {
                        // Log exception and return an empty string
                        logger()->error($this->vendor.'.'.$this->store->code.': Error when exporting product '.$product->id.' for feed. '.$ex->getMessage().' '.$ex->getFile().' '.$ex->getLine()."\n");

                        return '';
                    }

                    return null;
                });
            // Implode the array of rows into a single string
            $feedLines = $feedLines->implode('');

            // Append the feed rows for the product chunk to the feed file
            File::append($this->filePath, $feedLines);
        });

        // Get amount of products in feed (file line count - 2 for header and empty line at bottom)
        $actualProductCount = File::lines($this->filePath)->count() - 2;

        logger()->info($this->vendor.'.'.$this->store->code.': Finished with file line count '.$actualProductCount.', should be '.$expectedProductCount.' products');

        // If line count in feed is not right, don't proceed to upload to storage
        if ($actualProductCount !== $expectedProductCount) {
            throw new Error($this->vendor.'.'.$this->store->code.': Feed should contain '.$expectedProductCount.' products, but instead contains '.$actualProductCount.' products. Cancelling upload.');
        }

        $this->uploadToStorage();
        $this->removeLocalFile();
    }

    protected function uploadToStorage(): void
    {
        if(Storage::disk($this->feedsDisk)->directoryMissing('feeds')) {
            Storage::disk($this->feedsDisk)->makeDirectory('feeds');
        }

        // Prepare path to file
        $targetDirectory = 'feeds/'.$this->store->code.'/'.$this->vendor;
        $targetFileName = $this->vendor.'.'.$this->type;
        $targetPath = $targetDirectory.'/'.$targetFileName;

        $currentFeedExists = false;

        try {
            $currentFeedExists = Storage::disk($this->feedsDisk)->exists($targetPath);
        } catch (Exception $e) {
        }

        // If the currently active feed file was found
        if ($currentFeedExists) {
            // Get the formatted date for when the currently active feed file was last modified and prepare a new filename using it
            $lastModifiedDate = Storage::disk($this->feedsDisk)->lastModified($targetPath);
            $lastModifiedDate = Carbon::createFromTimestamp($lastModifiedDate)->startOfDay();

            // If currently active feed was not created today, back it up.
            if ($lastModifiedDate->ne(today())) {
                $newNameForOldFeed = $this->vendor.'_'.$lastModifiedDate->toDateString().'.'.$this->type;

                if (! Storage::disk($this->feedsDisk)->exists($targetDirectory.'/'.$newNameForOldFeed)) {
                    Storage::disk($this->feedsDisk)->copy($targetPath, $targetDirectory.'/'.$newNameForOldFeed);
                }
            }
        }

        Storage::disk($this->feedsDisk)->put($targetPath, File::get($this->filePath), FileVisibility::PUBLIC);
    }

    protected function removeLocalFile(): void
    {
        File::delete($this->filePath);
    }

    protected function formatFeedLine(array $fields)
    {
        switch ($this->type) {
            case 'txt':
                return $this->convertToTxtLine($fields);
                break;
            case 'csv':
                return $this->convertToCsvLine($fields);
                break;
        }
    }

    protected function convertToTxtLine(array $fields): string
    {
        $fields = array_map([$this, 'cleanValue'], $fields);

        $productLine = implode("\t", $fields);
        $productLine .= "\n";

        return $productLine;
    }

    protected function convertToCsvLine(array $fields): string
    {
        // Get cleaned-up fields
        $fields = array_map([$this, 'cleanValue'], $fields);

        // Open a file in memory (max 1MB) and write the fields to them as CSV
        $handle = fopen('php://temp/maxmemory:1048576', 'w');
        fputcsv($handle, $fields);

        // Rewind the position of the file handle
        rewind($handle);

        // Get the contents of the in-memory file
        $productLine = stream_get_contents($handle);

        // Close the file handle
        fclose($handle);

        // Return the contents of the in-memory file
        return $productLine;
    }

    public function cleanValue($value): string
    {
        return trim(strip_tags(str_replace(["\t", "\n", "\r"], ' ', $value)));
    }

    /**
     * The job failed to process.
     */
    public function failed(Exception|Error $exception): void
    {
        // TODO: uncomment line below
//        $this->removeLocalFile();
        event(new FeedJobFailed($exception));
    }

    public function getDelivery($product): string
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

    protected function getHost(): string
    {
        return $this->protocol.$this->store->domain;
    }

    protected function margeMapper($marge): int
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

    protected function getGrossMargin($product): float|string
    {
        if ($product->cost_price > 0) {
            $currentPriceExcludingVat = optional($product->getCurrentPrice())->price_excluding_vat;
            if ($currentPriceExcludingVat && $currentPriceExcludingVat > 0) {
                return round((($currentPriceExcludingVat - $product->cost_price) / $currentPriceExcludingVat) * 100,
                    0);
            }
        }

        return '';
    }

    protected function getInStock(Product $product): int
    {
        return ($product->stock) ? $product->stock->sum('in_stock') : 0;
    }

    protected function getTag(Product $product): ?string
    {
        $tag = $product->tags()->where('name', 'like', 'G:%')->first();

        return ($tag) ? $tag->name : null;
    }

    protected function getImageLink(Product $product): ?string
    {
        $image = $product->images()->first();

        return optional($image)->src;
    }

    protected function getCategories(Product $product): string
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

    public function uniqueId(): string
    {
        return $this->vendor.$this->store->code;
    }

    public function getProductsCount($query)
    {
        return $query->count();
    }

    public function postProcessFeedRow($feedRow, $product)
    {
        $afterCreatingFeedRowEvent = new AfterCreatingFeedRow($this->vendor, $this->store, $feedRow, $product);
        event($afterCreatingFeedRowEvent);
        $feedRow = $afterCreatingFeedRowEvent->getFeedRow();

        // Overwrite preconfigured fields for this vendor
        $fieldOverwrites = config('daalder-feeds.field-overwrites.'.$this->vendor);
        if ($fieldOverwrites) {
            foreach ($fieldOverwrites as $field => $value) {
                $feedRow[$field] = $value;
            }
        }

        // Format and return the feed row
        return $this->formatFeedLine($feedRow);
    }
}
