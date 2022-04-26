<?php

namespace Daalder\Feeds\Tests;

use Daalder\Feeds\Jobs\Feeds\Feed;
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

    private $localFeedFilePath;

    public abstract function it_can_generate_feed();

    protected function generate_feed_basetest($feed, $store)
    {
        try {
            /** @var Feed $feedJob */
            $feedJob = new $feed($store);
            $feedJob::dispatchSync($store);
            $this->localFeedFilePath = $feedJob->filePath;
        } catch(\InvalidArgumentException $e) {
            // AWS credentials aren't configured
        }

        $this->assertFileExists($this->localFeedFilePath);
    }

    protected function getProductsCountInFeedFile(string $filePath) {
        // Line count - 2 lines (header with column names, empty line at the end of the file)
        return File::lines($filePath)->count() - 2;
    }
}
