<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Illuminate\Support\Str;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Shipping\ShippingMethod;
use Pionect\Daalder\Services\MoneyFactory;
/**
 * @see https://prisguiden.no/om-oss/nyheter-og-presse/slik-gjoer-du-produktene-tilgjengelig-paa-prisguiden-277
 * Class PrisguidenFeed
 */
class PrisguidenFeed extends Feed
{
    /** @var string */
    public $type = 'csv';

    /** @var string */
    public $vendor = 'prisguiden';

    /** @var string[] */
    public $fieldNames = [
        'name',
        'price',
        'url',
        'image_url',
        'id',
        'manufacturer_id',
        'ean',
        'stock_status',
        'total_in_stock',
        'in_stock_date',
        'shipping_cost',
        'category',
        'description',
    ];

    protected function productToFeedRow(Product $product) {
        $host = $this->getHost();

        $priceObject = $product->getCurrentPrice();
        $currency = optional(optional($priceObject)->currency)->code ?? $this->getCurrency($product);
        $countryCode = $this->getCountryCode();

        $shipping = '';
        /** @var ShippingMethod $rate */
        $rate = null;

        if ($product->shippingTier && $product->shippingTier->methods) {
            $rate = $product->shippingTier->methods->where('country_code', $countryCode)->first();
        }

        if ($rate) {
            $shipping = "{$countryCode}:";
            $shipping .= ':';
            $shipping .= ':';
            $shipping .= MoneyFactory::toString($rate->price).' '.$currency;
        }

        $fields = [
            'name' => $product->name,
            'price' => $this->getFormattedPrice($priceObject),
            'url' => $host.'/'.$product->url,
            'image_url' => $this->getImageLink($product),
            'id' => $product->id,
            'manufacturer_id' => $product->sku,
            'ean' => $product->ean,
            'stock_status' => $this->getInStock($product) === 0 ? 'not_in_stock' : 'in_stock',
            'total_in_stock' => $this->getInStock($product),
            'in_stock_date' => '',
            'shipping_cost' => $shipping,
            'category' => $this->getCategories($product),
            'description' => Str::limit($product->description, 1000, '...'),
        ];

        return $fields;
    }
}
