<?php

namespace Daalder\Feeds\Services;

use Illuminate\Support\Str;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Store\Store;
use Pionect\Daalder\Services\MoneyFactory;

class FeedPriceFormatter


{
    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function getCountryCode()
    {
        return Str::upper(Str::after($this->store->defaultlanguage, '_'));
    }

    public function getCurrency(Product $product)
    {
        return optional(optional($product->getCurrentPrice())->currency)->code ?? $this->store->currency_code;
    }

    public function getFormattedPrice(Product $product)
    {
        $price = $product->getCurrentPrice();

        return $this->formatPrice($product, optional($price)->priceAsMoney());
    }

    public function getFormattedListPrice(Product $product)
    {
        $price = $product->getCurrentListPrice();

        return $this->formatPrice($product, optional($price)->listPriceAsMoney());
    }

    public function formatPrice(Product $product, $price = null)
    {
        if (!$price) {
            return '';
        }

        $currency = $this->getCurrency($product);

        return MoneyFactory::toString($price).' '.$currency;
    }

}
