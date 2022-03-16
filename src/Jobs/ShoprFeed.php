<?php

namespace App\Jobs\Feeds;

use Pionect\Daalder\Models\Category\Category;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Services\MoneyFactory;

class ShoprFeed extends Feed
{
    public $fieldNames = [
        'title',
        'price',
        'url',
        'shop_product_id',
        'category',
        'delivery_time',
        'additional_costs',
        'image_1',
        'group_id', //PRODUCT VARIATION ID
        'gtin', //EAN
        'brand',
        'description',
    ];

    protected function generate()
    {
        $this->generateFeed(
            'csv',
            'shopr',
            function (Product $product) {
                $host = $this->protocol.$this->store->domain;

                $prodPrice = optional($product->getCurrentPrice())->priceAsMoney();
                $prodPrice = $prodPrice ? MoneyFactory::toFloat($prodPrice) : 0;
                $shippingCost = 0;

                if ($prodPrice < 750) {
                    if ($prodPrice >= 150) {
                        $shippingCost = 49;
                    } else {
                        if ($prodPrice >= 25) {
                            $shippingCost = 19;
                        } else {
                            $shippingCost = 5;
                        }
                    }
                }

                $fields = [
                    'title' => $product->name,
                    'price' => $prodPrice,
                    'url' => $host,
                    'shop_product_id' => $product->id,
                    'category' => '', //gets filled later
                    'delivery_time' => $product->shippingTime->name ?? $this->getDelivery($product),
                    'additional_costs' => $shippingCost,
                    'image_1' => '', //gets filled later
                    'group_id' => '', //gets filled later
                    'gtin' => $product->ean,
                    'brand' => '', //gets filled later
                    'description' => strip_tags($product->description),
                ];

                /** @var Category $category */
                $category = $product->categories->first();

                if ($category) {
                    $fields['category'] = $category->name;
                    // TODO: @Karsten moet die hierarchy weer terug, zo ja, kan je daar dan even een task voor aanmaken?
//                    foreach ($page->getCategoryHierarchy() as $node) {
//                        $fields['category'] .= $node->name . ' > ';
//                    }

//                    $fields['category'] = rtrim($fields['category'], ' > ');
                }

                if (empty($fields['category'])) {
                    return false;
                }

                if (!is_null($product->brand)) {
                    $fields['brand'] = $product->brand->name;
                }

                $image = $product->images()
                    ->first();

                if ($image) {
                    $fields['image_1'] = $image->src;
                } else {
                    return false;
                }

                $variation = $product->productvariations->first();

                if ($variation) {
                    $fields['group_id'] = $variation->id;
                }

                return $fields;
            }
        );
    }
}
