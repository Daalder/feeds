<?php

namespace Daalder\Feeds\Tests\Jobs;

use Daalder\Feeds\Jobs\Feeds\AdmarktFeed;
use Daalder\Feeds\Tests\FeedsTestBase;
use Pionect\Daalder\Models\Store\Store;
use function storage_path;

/**
 * Class AdmarktFeedTest
 * @package Daalder\Feeds\Tests\Jobs
 */
class AdmarktFeedTest extends FeedsTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAdmarktTestProducts();
    }

    /** @test */
    public function it_can_generate_feed()
    {
        $this->generate_feed_basetest(AdmarktFeed::class, Store::first());
    }

    /** @test */
    public function it_contains_all_valid_products()
    {
        $store = Store::first();

        try {
            $feedJob = new AdmarktFeed($store);
            $feedJob::dispatchSync($store);
        } catch(\InvalidArgumentException $e) {
            // AWS credentials aren't configured
        }

        // The directory for this vendor should be created
        $this->assertDirectoryExists(storage_path('feeds/admarkt'));

        // Get the filePath for this vendor/feed combination (it's suffixed with a random string)
        $filePath = $this->getFeedFilePath('admarkt', $store->code);

        // There are 10 products, 8 of which should be in the feed (plus one row for header and one for an empty line at the bottom)
        $this->assertEquals($this->validTestProducts, $this->getProductsCountInFeedFile($filePath));
    }
}
