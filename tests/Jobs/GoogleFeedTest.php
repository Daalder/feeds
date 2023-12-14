<?php

namespace Daalder\Feeds\Tests\Jobs;

use Daalder\Feeds\Jobs\Feeds\GoogleFeed;
use Daalder\Feeds\Tests\FeedsTestBase;
use InvalidArgumentException;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\ProductAttribute\ProductAttribute;
use Pionect\Daalder\Models\Store\Store;
use function storage_path;

/**
 * Class GoogleFeedTest
 * @package Daalder\Feeds\Tests\Jobs
 */
class GoogleFeedTest extends FeedsTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createGoogleTestProducts();
    }

    /** @test */
    public function it_can_generate_feed()
    {
        $this->generate_feed_basetest(GoogleFeed::class, Store::first());
    }

    /** @test */
    public function it_contains_all_valid_products()
    {
        $store = Store::first();

        try {
            $feedJob = new GoogleFeed($store);
            $feedJob::dispatchSync($store);
        } catch(InvalidArgumentException $e) {
            // AWS credentials aren't configured
        }

        // The directory for this vendor should be created
        $this->assertDirectoryExists(storage_path('feeds/google'));

        // Get the filePath for this vendor/feed combination (it's suffixed with a random string)
        $filePath = $this->getFeedFilePath('google', $store->code);

        // There are 10 products, 8 of which should be in the feed (plus one row for header and one for an empty line at the bottom)
        $this->assertEquals($this->validTestProducts, $this->getProductsCountInFeedFile($filePath));
    }
    
    /** @test */
    public function it_omits_products_that_are_notforsale(): void
    {
        $store = Store::first();

        $notForSaleCount = 3;

        $products = Product::whereIn('id', $this->products->pluck('id')->toArray())
            ->whereHas('productproperties', function ($query): void {
                $query
                    ->join(ProductAttribute::table(), 'productattribute_id', '=', ProductAttribute::table().'.id')
                    ->where('code', 'include-in-google-feed')
                    ->where('value', 1);
            })
            ->get();

        $products
            ->random($notForSaleCount)
            ->each(fn (Product $product) => $product->fill(['is_for_sale' => 0])->save());

        try {
            (new GoogleFeed($store))::dispatchSync($store);
        } catch (InvalidArgumentException) {}

        $filePath = $this->getFeedFilePath('google', $store->code);

        $this->assertEquals($this->validTestProducts - $notForSaleCount, $this->getProductsCountInFeedFile($filePath));
    }
}