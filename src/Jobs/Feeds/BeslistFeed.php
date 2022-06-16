<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Services\MoneyFactory;

class BeslistFeed extends Feed
{
    /** @var string */
    public $type = 'txt';

    /** @var string */
    public $vendor = 'beslist';

    /** @var string[] */
    public $fieldNames = [
        'id',
        'ean',
        'title',
        'description',
        'link',
        'image_link',
        'price',
        'sale_price',
        'currency',
        'availability',
        'shipping_weight',
        'expiration_date',
        'condition',
        'payment_accepted',
        'brand',
        'category',
        'delivery_period_nl',
        'delivery_period_be',
    ];

    protected function productToFeedRow(Product $product)
    {
        $fields = [
            'id' => $product->id,
            'ean' => $product->ean,
            'title' => $product->name,
            'description' => $product->description,
            'link' => $this->getHost().'/'.$product->url,
            'image_link' => '',
            'price' => $this->priceFormatter->getFormattedPrice($product),
            'sale_price' => $this->priceFormatter->getFormattedListPrice($product),
            'currency' => $this->priceFormatter->getCurrency($product),
            'availability' => 'in stock',
            'shipping_weight' => $product->weight,
            'expiration_date' => date('d-m-Y', strtotime('+2 weeks')),
            'condition' => 'new',
            'payment_accepted' => 'IDEAL,Paypal,BanContact Mister Cash,Bankoverschrijving',
            'brand' => '',
            'category' => $product->productattributeset ? $product->productattributeset->name : null,
            'delivery_period_nl' => $product->shippingTime->name ?? $this->getDelivery($product),
            'delivery_period_be' => $product->shippingTime->name ?? $this->getDelivery($product),
        ];

        if (!is_null($product->brand)) {
            $fields['brand'] = $product->brand->name;
        }

        $image = $product->images()
            ->first();

        if ($image) {
            $fields['image_link'] = $image->src;
        }

        //TODO don't hard code id for beslist
        $categories = $product->feedCategories()
            ->where('feed_consumer_id', 3)
            ->orderBy('feed_category_product.id')
            ->get();

        $path = '';
        foreach ($categories as $category) {
            $path .= $category->name.', ';
        }

        $fields['category'] = rtrim($path, ', ');

        return $fields;
    }
}
