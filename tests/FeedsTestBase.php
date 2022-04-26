<?php

namespace Daalder\Feeds\Tests;

use Daalder\Feeds\Jobs\Feeds\Feed;
use Daalder\Feeds\Jobs\Feeds\GoogleFeed;
use Daalder\Feeds\Tests\TestCase as DaalderTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pionect\Daalder\Models\Store\Store;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class FeedsTestBase
 * @package Daalder\Feeds\Tests
 */
abstract class FeedsTestBase extends DaalderTestCase
{
    use CreatesTestProducts;

    private $localFeedFilePath;

    public abstract function it_can_generate_feed();

    protected function generate_feed_basetest($feed, $store)
    {
        try {
            /** @var Feed $feedJob */
            $feedJob = new $feed($store);
            $feedJob::dispatchSync($store);
        } catch(\InvalidArgumentException $e) {
            // AWS credentials aren't configured
        }

        // The directory for this vendor should be created
        $this->assertDirectoryExists(storage_path('feeds/'.$feedJob->vendor));

        // Get the filePath for this vendor/feed combination (it's suffixed with a random string)
        $filePath = $this->getFeedFilePath($feedJob->vendor, $store->code);

        // Feed file should be created
        $this->assertFileExists($filePath);
    }

    protected function getFeedFilePath(string $vendor, string $store) {
        $vendorFiles = File::files(storage_path("feeds/$vendor"));

        $vendorStoreFile = collect($vendorFiles)
            ->filter(function(SplFileInfo $file) use ($store) {
                return Str::contains($file->getFilename(), $store);
            })
            ->first()
            ->getFileName();

        return storage_path("feeds/$vendor/$vendorStoreFile");
    }

    protected function getProductsCountInFeedFile(string $filePath) {
        // Line count - 2 lines (header with column names, empty line at the end of the file)
        return File::lines($filePath)->count() - 2;
    }
}
