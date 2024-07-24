<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Services\MoneyFactory;

class TradeTrackerFeed extends Feed
{
    /** @var string */
    public $type = 'csv';

    /** @var string */
    public $vendor = 'tradetracker';

    /** @var string[] */
    public $fieldNames = [
        'ID',
        'productURL',
        'imageURL',
        'imageURL',
        'imageURL',
        'imageURL',
        'imageURL',
        'name',
        'description',
        'price',
        'fromPrice',
        'discount',
        'deliveryTime',
        'brand',
        'categoryPath',
        'categories',
        'subcategories',
        'subsubcategories',
        'deliveryCosts',
        'EAN',
    ];

    protected function productToFeedRow(Product $product)
    {
        $priceObject = $product->getCurrentPrice();
        $priceAsMoney = optional($priceObject)->priceAsMoney();
        $listPriceAsMoney = optional($priceObject)->priceAsMoney();

        // Temporary fix for daalder ~13.15.5
        if ($priceObject->list_price == $priceObject->price) {
            $listPriceAsMoney = null;
        }

        if (is_null($listPriceAsMoney)) {
            $price = $priceAsMoney ? MoneyFactory::toFloat($priceAsMoney) : '';
            $fromPrice = '';
            $discountPercentage = '';
        } else {
            $price = $listPriceAsMoney ? MoneyFactory::toFloat($listPriceAsMoney) : 0;
            $fromPrice = $priceAsMoney ? MoneyFactory::toFloat($priceAsMoney) : 0;

            if ($fromPrice > 0) {
                $discountPercentage = (($fromPrice - $price) * 100) / $fromPrice;
            } else {
                $fromPrice = '';
                $discountPercentage = '';
            }
        }

        // set bol price
        $shippingCost = 0;
        $prodPrice = $priceAsMoney ? MoneyFactory::toFloat($priceAsMoney) : 0;

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
            'ID' => $product->id,
            'productURL' => $this->getHost().'/'.$product->url,
            'imageURL-1' => '', //Filled below
            'imageURL-2' => '',
            'imageURL-3' => '',
            'imageURL-4' => '',
            'imageURL-5' => '',
            'name' => $product->name,
            'description' => $product->description,
            'price' => $price,
            'fromPrice' => $fromPrice,
            'discount' => $discountPercentage,
            'deliveryTime' => $product->shippingTime->name ?? $this->getDelivery($product),
            'brand' => '',
            'categoryPath' => '',
            'categories' => '',
            'subcategories' => '',
            'subsubcategories' => '',
            'deliveryCosts' => $shippingCost,
            'EAN' => $product->ean,
        ];

        if (!is_null($product->brand)) {
            $fields['brand'] = $product->brand->name;
        }

        $images = $product->images()->limit(5)->map->getPublicUrl()->all();

        foreach ($images as $key => $image) {
            $fields['imageURL-'.($key + 1)] = $image;
        }

        $categories = $product->feedCategories()
            ->whereNull('feed_consumer_id')
            ->orderBy('feed_category_product.id')
            ->get();

        if (!is_null($categories)) {
            $path = '';
            $level = 0;

            /* @var $category \Pionect\Daalder\Models\Feed\Category */
            foreach ($categories as $category) {
                switch ($level) {
                    case 0:
                        $fields['categories'] = $category->name;
                        break;
                    case 1:
                        $fields['subcategories'] = $category->name;
                        break;
                    case 2:
                        $fields['subsubcategories'] = $category->name;
                        break;
                }
                $path .= $category->name.' > ';

                $level++;
            }

            $fields['categoryPath'] = rtrim($path, ' >');
        }

        return $fields;
    }
}
