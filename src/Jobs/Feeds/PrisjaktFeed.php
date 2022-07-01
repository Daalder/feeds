<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Shipping\ShippingMethod;
use Pionect\Daalder\Services\MoneyFactory;

/**
 * @see https://pricespy.co.uk/info/register-and-feature-your-shop--i10
 * @see https://www.prisjakt.no/info/registrer-og-profiler-butikken-din--i10
 * Class PricespyFeed
 */
class PrisjaktFeed extends Feed
{
    /** @var string */
    public $type = 'csv';

    /** @var string */
    public $vendor = 'prisjakt';

    /** @var string[] */
    public $fieldNames = [
         'id',
         'category',
         'brand',
         'name',
         'condition',
         'sku',
         'url',
         'image_links',
         'shipping_price',
         'description',
         'stock_status',
         'availability',
    ];

    protected function productToFeedRow(Product $product) {
        $host = $this->getHost();
        $currency = $this->priceFormatter->getCurrency($product);
        $countryCode = $this->priceFormatter->getCountryCode();

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
            'id' => $product->id,
            'category' => $this->getCategories($product),
            'brand' => optional($product->brand)->name,
            'name' => $product->name,
            'condition' => 'Ny',
            'sku' => $product->sku,
            'url' => $host.'/'.$product->url,
            'image_links' => $this->getImageLink($product),
            'shipping_price' => $shipping,
            'description' => $product->description,
            'stock_status' => $this->getInStock($product) === 0 ? 'Ikke på lager' : 'På lager',
            'availability' => $this->getInStock($product) === 0 ? 'Kan ikke bestilles' : 'Tilgjengelig',
        ];

        return $fields;
    }
}
