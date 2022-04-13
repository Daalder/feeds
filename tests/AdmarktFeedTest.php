<?php

namespace Daalder\Feeds\Tests;

use Daalder\Feeds\Jobs\Feeds\AdmarktFeed;
use Pionect\Daalder\Models\Store\Store;

/**
 * Class AdmarktFeedTest
 * @package Daalder\Feeds\Tests
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

        $fileName = $store->code.'.'.$feedJob->type;
        $localFilePath = storage_path().'/feeds/'.$feedJob->vendor.'/'.$fileName;

        // There are 10 products, 8 of which should be in the feed (plus one row for header and one for an empty line at the bottom)
        $this->assertEquals($this->validTestProducts, $this->getProductsCountInFeedFile($localFilePath));
    }
}
