<?php

namespace Daalder\Feeds\Tests;

use Daalder\Feeds\Jobs\Feeds\GoogleFeed;
use Pionect\Daalder\Models\Media\Media;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Product\ProductProperty;
use Pionect\Daalder\Models\Product\Repositories\ProductRepository;
use Pionect\Daalder\Models\ProductAttribute\ProductAttribute;
use Pionect\Daalder\Models\ProductAttribute\Set;
use Pionect\Daalder\Models\Store\Store;

trait CreatesTestProducts {
    public int $validTestProducts;

    private function createGoogleAttributeProperty() {
        $attribute = ProductAttribute::factory()->create([
            'code' => 'include-in-google-feed',
            'name' => 'Include in Google Feed',
            'default_value' => 1,
            'inputtype' => 'boolean',
            'is_global' => true,
        ]);

        return ProductProperty::factory()->create([
            'productattribute_id' => $attribute->id
        ]);
    }

    protected function createGoogleTestProducts() {
        $includeInGoogleFeedProperty = $this->createGoogleAttributeProperty();

        // Two products without images
        Product::factory()->count(2)->create();

        // Ten products with images
        $products = Product::factory()->count(10)
            ->hasImages(Media::factory()->create())
            ->create();

        // One product per attributeset in the excludedGoogleAttributeSets array
        foreach((new GoogleFeed(Store::first()))->excludedGoogleAttributeSets as $blockedAttributeSetId) {
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
        foreach(Product::get() as $product) {
            app(ProductRepository::class)->setPropertyValue($product, $includeInGoogleFeedProperty->id, 1);
        }

        // Two of those ten products have a value of 0 for the include-in-google-feed property
        foreach($products->random(2) as $product) {
            app(ProductRepository::class)->setPropertyValue($product, $includeInGoogleFeedProperty->id, 0);
        }

        $this->validTestProducts = 8;
    }

    protected function createAdmarktTestProducts() {
        // Two products without images
        Product::factory()->count(2)->create();

        // Ten products with images
        $products = Product::factory()->count(10)
            ->hasImages(Media::factory()->create())
            ->create();

        $products->random(2)->update([
            'delivery' => 55,
        ]);

        $this->validTestProducts = 8;
    }
}
