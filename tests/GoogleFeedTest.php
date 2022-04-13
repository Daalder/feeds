<?php

namespace Daalder\Feeds\Tests;

use Daalder\Feeds\Jobs\Feeds\GoogleFeed;
use Daalder\Feeds\Tests\TestCase as DaalderTestCase;
use Pionect\Daalder\Models\Store\Store;

/**
 * Class GoogleFeedTest
 * @package Daalder\Feeds\Tests
 */
class GoogleFeedTest extends DaalderTestCase
{
    use CreatesTestProducts;

    /** @test */
    public function can_generate_feed()
    {
        $this->createTestData();

        $store = Store::first();

        try {
            $feedJob = new GoogleFeed($store);
            $feedJob::dispatchSync();
        } catch(\Exception $e) {
            echo ";";
        }

        $fileName = $store->code.'.'.$feedJob->type;
        $localFilePath = storage_path().'/feeds/'.$feedJob->vendor.'/'.$fileName;
    }
}
