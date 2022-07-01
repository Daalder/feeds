<?php

namespace Daalder\Feeds\Tests\Jobs;

use Daalder\Feeds\Jobs\Feeds\NetrivalsFeed;
use Daalder\Feeds\Tests\FeedsTestBase;
use Pionect\Daalder\Models\Store\Store;
use function storage_path;

/**
 * Class NetrivalsFeedTest
 * @package Daalder\Feeds\Tests\Jobs
 */
class NetrivalsFeedTest extends FeedsTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createNetrivalsTestProducts();
    }

    /** @test */
    public function it_can_generate_feed()
    {
        $this->generate_feed_basetest(NetrivalsFeed::class, Store::first());
    }

    /** @test */
    public function it_contains_all_valid_products()
    {
        $store = Store::first();

        try {
            $feedJob = new NetrivalsFeed($store);
            $feedJob::dispatchSync($store);
        } catch(\InvalidArgumentException $e) {
            // AWS credentials aren't configured
        }

        // The directory for this vendor should be created
        $this->assertDirectoryExists(storage_path('feeds/netrivals'));

        // Get the filePath for this vendor/feed combination (it's suffixed with a random string)
        $filePath = $this->getFeedFilePath('netrivals', $store->code);

        // There are 10 products, 8 of which should be in the feed (plus one row for header and one for an empty line at the bottom)
        $this->assertEquals($this->validTestProducts, $this->getProductsCountInFeedFile($filePath));
    }
}
