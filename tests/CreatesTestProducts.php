<?php

namespace Daalder\Feeds\Tests;

use Daalder\Feeds\Jobs\Feeds\BolFeed;
use Daalder\Feeds\Jobs\Feeds\GoogleFeed;
use Pionect\Daalder\Models\Category\Category;
use Pionect\Daalder\Models\Media\Media;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Product\ProductProperty;
use Pionect\Daalder\Models\Product\ProductSelection;
use Pionect\Daalder\Models\Product\Repositories\ProductRepository;
use Pionect\Daalder\Models\ProductAttribute\ProductAttribute;
use Pionect\Daalder\Models\ProductAttribute\Set;
use Pionect\Daalder\Models\Store\Store;
use Pionect\Daalder\Models\Supplier\Supplier;

trait CreatesTestProducts
{
    public int $validTestProducts;
    public $products;

    private function createFeedAttributeProperty($code, $name)
    {
        $attribute = ProductAttribute::factory()->create([
            'code' => $code,
            'name' => $name,
            'default_value' => 1,
            'inputtype' => 'boolean',
            'is_global' => true,
        ]);

        return ProductProperty::factory()->create([
            'productattribute_id' => $attribute->id
        ]);
    }

    protected function createBaseProduct()
    {
        // Two products without images
        Product::factory()->count(2)->create();

        // Ten products with images
        $this->products = Product::factory()->count(10)
            ->hasImages(Media::factory()->create())
            ->create();

    }

    protected function createGoogleTestProducts()
    {
        $includeInGoogleFeedProperty = $this->createFeedAttributeProperty('include-in-google-feed', 'Include in Google Feed');
        // Get 12 products, with 2 without images
        $this->createBaseProduct();


        // One product per attributeset in the excludedGoogleAttributeSets array
        foreach ((new GoogleFeed(Store::first()))->excludedGoogleAttributeSets as $blockedAttributeSetId) {
            Set::factory()->create([
                'id' => $blockedAttributeSetId,
            ]);

            Product::factory()
                ->hasImages(Media::factory()->create())
                ->create([
                    'productattributeset_id' => $blockedAttributeSetId,
                ]);
        }

        // Set the include-in-google-feed property to 1 for all products
        foreach (Product::get() as $product) {
            app(ProductRepository::class)->setPropertyValue($product, $includeInGoogleFeedProperty->id, 1);
        }

        // Two of those ten products have a value of 0 for the include-in-google-feed property
        foreach ($this->products->random(2) as $product) {
            app(ProductRepository::class)->setPropertyValue($product, $includeInGoogleFeedProperty->id, 0);
        }

        $this->validTestProducts = 8;
    }

    protected function createGoogleLocalInventoryTestProducts()
    {
        $this->createGoogleTestProducts();
        config(['daalder-feeds.main-google-store' => [
            'store-code' => 23,
//            'main-pickup-point-id' => 1,
        ]]);

        $this->validTestProducts = 8;
    }

    protected function createAdmarktTestProducts()
    {
        // Get 12 products, with 2 without images
        $this->createBaseProduct();

        // Two products with a blocked delivery
        $deliveryBlockedProducts = $this->products->random(2);
        $deliveryBlockedProducts->each->update([
            'delivery' => 55,
        ]);

        $this->validTestProducts = 8;
    }

    protected function createFacebookTestProducts()
    {
        // Get 12 products, with 2 without images
        $this->createBaseProduct();
        $includeInFacebookFeedProperty = $this->createFeedAttributeProperty('include-in-facebook-feed', 'Include in Facebook Feed');

        // One product per attributeset in the excludedGoogleAttributeSets array
        foreach ((new GoogleFeed(Store::first()))->excludedGoogleAttributeSets as $blockedAttributeSetId) {
            Set::factory()->create([
                'id' => $blockedAttributeSetId,
            ]);

            Product::factory()
                ->hasImages(Media::factory()->create())
                ->create([
                    'productattributeset_id' => $blockedAttributeSetId,
                ]);
        }

        // One not for sale product
        Product::factory()
            ->hasImages(Media::factory()->create())
            ->create([
                'is_for_sale' => 0,
            ]);

        // Set the include-in-facebook-feed property to 1 for all products
        foreach (Product::get() as $product) {
            app(ProductRepository::class)->setPropertyValue($product, $includeInFacebookFeedProperty->id, 1);
        }

        // Two of those ten products have a value of 0 for the include-in-facebook-feed property
        foreach ($this->products->random(2) as $product) {
            app(ProductRepository::class)->setPropertyValue($product, $includeInFacebookFeedProperty->id, 0);
        }

        $this->validTestProducts = 8;
    }

    protected function createBeslistTestProducts()
    {
        // Get 12 products, with 2 without images
        $this->createBaseProduct();

        $this->validTestProducts = 10;
    }

    protected function createBolTestProducts()
    {
        // Get 12 products, with 2 without images
        $this->createBaseProduct();

        // Get 8 random products out of the 10 with images & assign their
        // exclude_bol_export attribute to  0 and get their brand Ids
        $brandIds = $this->products->random(8)->each(function ($product) {
            $product->exclude_bol_export = 0;
            $product->save();

        })->pluck('brand_id');

        // Create two approved suppliersa
        foreach (collect((new BolFeed(Store::first()))->supplierRates)->keys()->random(2) as $supplierName) {
            Supplier::factory()->create(['name' => $supplierName]);
        };
        $suppliers = Supplier::all();
        // Connect each one of them with half of the brandIds
        $suppliers->first()->brands()->sync($brandIds->pad(4, 0)->all());
        $suppliers->last()->brands()->sync($brandIds->pad(-4, 0)->all());


        $this->validTestProducts = 8;
    }

    protected function createNetrivalsTestProducts()
    {
        // Get 12 products, with 2 without images
        $this->createBaseProduct();
        $includeInNetrivalsFeedProperty = $this->createFeedAttributeProperty('include-in-netrivals-feed', 'Include in Netrivals Feed');

        // Set the include-in-netrivals-feed property to 1 for all products
        foreach (Product::get() as $product) {
            app(ProductRepository::class)->setPropertyValue($product, $includeInNetrivalsFeedProperty->id, 1);
        }

        // Two of those ten products have a value of 0 for the include-in-netrivals-feed
        foreach ($this->products->random(2) as $product) {
            app(ProductRepository::class)->setPropertyValue($product, $includeInNetrivalsFeedProperty->id, 0);
        }

        $this->validTestProducts = 8;
    }

    protected function createPrisguidenTestProducts()
    {
        // Get 12 products, with 2 without images
        $this->createBaseProduct();

        $this->validTestProducts = 10;
    }

    protected function createPrisjaktTestProducts()
    {
        // Get 12 products, with 2 without images
        $this->createBaseProduct();

        $this->validTestProducts = 10;
    }

    protected function createShoprTestProducts()
    {
        // Get 12 products, with 2 without images
        $this->createBaseProduct();
        // Get 8 random product ids
        $productCategories = $this->products->random(8)->pluck('id');

        // Create a product selection & attach the 8 random products to it
        $productSelection = ProductSelection::factory()->create();
        $productSelection->products()->sync($productCategories->all());

        // Create a category & attach the created productselection
        Category::factory()->create()->productselections()->save($productSelection);

        $this->validTestProducts = 8;
    }


}
