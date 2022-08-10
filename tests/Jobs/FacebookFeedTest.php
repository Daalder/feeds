<?php

namespace Daalder\Feeds\Tests\Jobs;

use Daalder\Feeds\Jobs\Feeds\FacebookFeed;
use Daalder\Feeds\Tests\FeedsTestBase;
use Pionect\Daalder\Models\Store\Store;
use function storage_path;

/**
 * Class FacebookFeedTest
 * @package Daalder\Feeds\Tests\Jobs
 */
class FacebookFeedTest extends FeedsTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFacebookTestProducts();
    }

    /** @test */
    public function it_can_generate_feed()
    {
        $this->generate_feed_basetest(FacebookFeed::class, Store::first());
    }

    /** @test */
    public function it_contains_all_valid_products()
    {
        $store = Store::first();

        try {
            $feedJob = new FacebookFeed($store);
            $feedJob::dispatchSync($store);
        } catch(\InvalidArgumentException $e) {
            // AWS credentials aren't configured
        }

        // The directory for this vendor should be created
        $this->assertDirectoryExists(storage_path('feeds/facebook'));

        // Get the filePath for this vendor/feed combination (it's suffixed with a random string)
        $filePath = $this->getFeedFilePath('facebook', $store->code);

        // There are 10 products, 8 of which should be in the feed (plus one row for header and one for an empty line at the bottom)
        $this->assertEquals($this->validTestProducts, $this->getProductsCountInFeedFile($filePath));
    }
}
