<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Store\Store;
use Pionect\Daalder\Services\MoneyFactory;

class AdmarktFeed extends Feed
{
    /** @var string */
    public $type = 'txt';

    /** @var string */
    public $vendor = 'admarkt';

    /** @var string[] */
    protected $fieldNames = [
        'id',
        'title',
        'description',
        'link',
        'price',
        'sale_price',
        'currency',
        'availability',
        'shipping_weight',
        'expiration_date',
        'condition',
        'product_type',
        'google_product_category',
        'payment_accepted',
        'gtin',
        'brand',
        'mpn',
        'adwords_label',
        'image1',
        'image2',
        'image3',
        'image4',
        'image5',
        'image6',
        'image7',
        'image8',
        'image9',
        'image10',
        'image11',
        'image12',
        'image13',
        'image14',
        'image15',
        'image16',
        'image17',
        'image18',
        'image19',
        'image20',
        'image21',
        'image22',
        'image23',
        'image24',
    ];

    protected function productToFeedRow(Product $product) {
        $shipping_weight = ($product->weight != '') ? $product->weight : 1;

        $fields = [
            'id' => $product->id,
            'title' => $product->name,
            'description' => $product->description,
            'link' => $this->getHost().'/'.$product->url,
            'price' => $this->getFormattedPrice($product),
            'sale_price' => $this->getFormattedListPrice($product),
            'currency' => $this->getCurrency($product),
            'availability' => 'in stock',
            'shipping_weight' => $shipping_weight,
            'expiration_date' => date('Y-m-d', strtotime('+2 weeks')),
            'condition' => 'new',
            'product_type' => '', //The category
            'google_product_category' => $product->productattributeset ? $product->productattributeset->name : null,
            'payment_accepted' => 'IDEAL,Paypal,BanContact Mister Cash,Bankoverschrijving',
            'gtin' => $product->ean,
            'brand' => '', //Filled below
            'mpn' => $product->sku,
            'adwords_label' => '', //Filled below
        ];

        if (!is_null($product->brand)) {
            $fields['brand'] = $product->brand->name;
        }

        if ($product->delivery == 51) {
            return false;
        }

        $i = 1;
        while ($i < 24) {
            $fields['image'.$i] = (isset($product->images[$i - 1])) ? $product->images[$i - 1]->src : '';
            $i++;
        }

        $categories = $product->feedCategories()
            ->whereNull('feed_consumer_id')
            ->orderBy('feed_category_product.id')
            ->get();

        $adwordsLabel = '';

        if (!is_null($categories)) {
            $path = '';

            /* @var $category \Pionect\Daalder\Models\Feed\Category */
            foreach ($categories as $category) {
                $path .= $category->name.' > ';

                $adwordsLabel = $category->name;
            }

            $fields['product_type'] = rtrim($path, ' >');
        }

        $fields['adwords_label'] = $adwordsLabel;

        return $fields;
    }
}