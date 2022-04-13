<?php

namespace Daalder\Feeds\Tests;

use Daalder\Feeds\Jobs\Feeds\GoogleFeed;
use Daalder\Feeds\Tests\TestCase as DaalderTestCase;
use Illuminate\Support\Facades\File;
use Pionect\Daalder\Models\Store\Store;

/**
 * Class GoogleFeedTest
 * @package Daalder\Feeds\Tests
 */
class GoogleFeedTest extends DaalderTestCase
{
    use CreatesTestProducts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestProducts();
    }

    /** @test */
    public function can_generate_feed()
    {
        $store = Store::first();

        try {
            $feedJob = new GoogleFeed($store);
            $feedJob::dispatchSync($store);
        } catch(\InvalidArgumentException $e) {
            // AWS credentials aren't configured
        }

        $fileName = $store->code.'.'.$feedJob->type;
        $localFilePath = storage_path().'/feeds/'.$feedJob->vendor.'/'.$fileName;

        // There are 10 products, 8 of which should be in the feed (plus one row for header and one for an empty line at the bottom)
        $this->assertEquals(10, File::lines($localFilePath)->count());
    }
}