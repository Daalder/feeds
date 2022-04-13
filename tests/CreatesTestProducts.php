<?php

namespace Daalder\Feeds\Tests;

use Daalder\Feeds\Jobs\Feeds\GoogleFeed;
use Illuminate\Database\Eloquent\Collection;
use Pionect\Daalder\Models\Media\Media;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Product\ProductProperty;
use Pionect\Daalder\Models\Product\Repositories\ProductRepository;
use Pionect\Daalder\Models\ProductAttribute\ProductAttribute;
use Pionect\Daalder\Models\Store\Store;

trait CreatesTestProducts {
    /** @var ProductProperty $includeProperty */
    private $googleFeedIncludeProperty;

    private function createAttributeProperty() {
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

    private function createTestProducts() {
        $includeInGoogleFeedProperty = $this->createAttributeProperty();

        // Two products without images
        Product::factory()->count(2)->create();

        // One product per attributeset in the excludedGoogleAttributeSets array
        foreach((new GoogleFeed(Store::first()))->excludedGoogleAttributeSets as $blockedAttributeSetId) {
            Product::factory()->hasProductattributeset([
                'id' => $blockedAttributeSetId,
            ]);
        }

        // Ten products with images
        $products = Product::factory()->count(10)
            ->hasImages(Media::factory()->create())
            ->create();

        // Two of those ten products have a value of 0 for the include-in-google-feed property
        foreach($products->random(2) as $product) {
            app(ProductRepository::class)->setPropertyValue($product, $includeInGoogleFeedProperty->id, 0);
        }
    }
}
