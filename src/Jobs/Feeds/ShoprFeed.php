<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Services\MoneyFactory;

class ShoprFeed extends Feed
{
    /** @var string */
    public $type = 'csv';

    /** @var string */
    public $vendor = 'shopr';

    /** @var string[] */
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

    protected function getProductQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getProductQuery();

        return $query->has('categories');
    }

    protected function productToFeedRow(Product $product)
    {
        $priceAsMoney = optional($product->getCurrentPrice())->priceAsMoney();
        $price = $priceAsMoney ? MoneyFactory::toFloat($priceAsMoney) : 0;
        $shippingCost = 0;

        if ($price < 750) {
            if ($price >= 150) {
                $shippingCost = 49;
            } else {
                if ($price >= 25) {
                    $shippingCost = 19;
                } else {
                    $shippingCost = 5;
                }
            }
        }

        $fields = [
            'title' => $product->name,
            'price' => $price,
            'url' => $this->getHost(),
            'shop_product_id' => $product->id,
            'category' => $product->categories->first()->name,
            'delivery_time' => $product->shippingTime->name ?? $this->getDelivery($product),
            'additional_costs' => $shippingCost,
            'image_1' => $product->images->first()->src,
            'group_id' => '', //gets filled later
            'gtin' => $product->ean,
            'brand' => '', //gets filled later
            'description' => strip_tags($product->description),
        ];

        if (! is_null($product->brand)) {
            $fields['brand'] = $product->brand->name;
        }

        $variation = $product->productvariations->first();

        if ($variation) {
            $fields['group_id'] = $variation->id;
        }

        return $fields;
    }
}
