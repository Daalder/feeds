<?php

namespace Daalder\Feeds\Services;

use Illuminate\Support\Arr;
use Pionect\Daalder\Models\Product\Product;

class VariationChecker
{
    protected array $productVariationIds = [];

    public function getVariationGroupString(Product $product): string
    {
        return Arr::get($this->productVariationIds, $product->id) ?: $this->getVariationIds($product);
    }

    private function getVariationIds(Product $product): string
    {
        if (!$product->hasVariations()) {
            return '';
        }

        $variationGroupString = (string)$product->id;

        $product->productVariationsProductIds()->each(function ($id) use ($variationGroupString) {
            $this->productVariationIds[$id] = $variationGroupString;
        });

        return $variationGroupString;
    }
}
