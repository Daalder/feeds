<?php

namespace Daalder\Feeds\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Store\Store;
use Pionect\Daalder\Services\MoneyFactory;

class VariationChecker
{
    /** @var array */
    protected $productVariationIds = [];

    /**
     * @param Product $product
     * @return string
     */
    public function getVariationGroupString(Product $product)
    {
        return Arr::get($this->productVariationIds, $product->id) ?: $this->getVariationIds($product);
    }

    /**
     * @param Product $product
     * @return string
     */
    private function getVariationIds(Product $product)
    {
        if (!$product->hasVariations()) {
            return '';
        }
        $variationGroupString = (string)$product->id;
        $product->variationProductIds()->each(function ($id) use ($variationGroupString) {
            $this->productVariationIds[$id] = $variationGroupString;
        });

        return $variationGroupString;
    }
}
