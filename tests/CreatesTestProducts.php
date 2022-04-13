<?php

namespace Daalder\Feeds\Tests;

use Illuminate\Database\Eloquent\Collection;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Product\ProductProperty;
use Pionect\Daalder\Models\Product\Repositories\ProductRepository;
use Pionect\Daalder\Models\ProductAttribute\ProductAttribute;

trait CreatesTestProducts {
    /** @var Collection */
    private $products;

    /** @var ProductProperty $includeProperty */
    private $googleFeedIncludeProperty;

    private function createTestData() {
        $this->createAttributeProperty();
        $this->createProducts();
    }

    private function createAttributeProperty() {
        $attribute = ProductAttribute::factory()->create([
            'code' => 'include-in-google-feed',
            'name' => 'Include in Google Feed',
            'default_value' => 1,
            'inputtype' => 'boolean',
            'is_global' => true,
        ]);

        $this->googleFeedIncludeProperty = ProductProperty::factory()->create([
            'productattribute_id' => $attribute->id
        ]);
    }

    private function createProducts() {
        $this->products = Product::factory()->count(10)->create();

        foreach($this->products->random(2) as $product) {
            app(ProductRepository::class)->setPropertyValue($product, $this->googleFeedIncludeProperty->id, 0);
        }
    }
}
