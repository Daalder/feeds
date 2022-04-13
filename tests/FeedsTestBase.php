<?php

namespace Daalder\Feeds\Tests;

use Daalder\Feeds\Jobs\Feeds\GoogleFeed;
use Daalder\Feeds\Tests\TestCase as DaalderTestCase;
use Illuminate\Support\Facades\File;
use Pionect\Daalder\Models\Store\Store;

/**
 * Class FeedsTestBase
 * @package Daalder\Feeds\Tests
 */
abstract class FeedsTestBase extends DaalderTestCase
{
    use CreatesTestProducts;

    public abstract function it_can_generate_feed();

    protected function generate_feed_basetest($feed, $store)
    {
        try {
            $feedJob = new $feed($store);
            $feedJob::dispatchSync($store);
        } catch(\InvalidArgumentException $e) {
            // AWS credentials aren't configured
        }

        $fileName = $store->code.'.'.$feedJob->type;
        $localFilePath = storage_path().'/feeds/'.$feedJob->vendor.'/'.$fileName;

        $this->assertFileExists($localFilePath);
    }

    protected function getProductsCountInFeedFile(string $filePath) {
        // Line count - 2 lines (header with column names, empty line at the end of the file)
        return File::lines($filePath)->count() - 2;
    }
}
