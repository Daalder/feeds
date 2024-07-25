<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Illuminate\Database\Eloquent\Builder;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Supplier\Supplier;
use Pionect\Daalder\Services\MoneyFactory;

class BolFeed extends Feed
{
    /** @var string */
    public $type = 'csv';

    /** @var string */
    public $vendor = 'bol';

    /** @var string[] */
    public $fieldNames = [
        'Productnaam',
        'Korte Omschrijving',
        'Omschrijving',
        'Levertijd',
        'Images',
        'EAN',
        'BOL prijs',
        'Commissie',
    ];

    /**@var array */
    public $supplierRates = [
        'KBT' => 9.5,
        'Westwood' => 8.5,
        'Woodvision' => 8.5,
        'Wienerberger' => 8.5,
    ];

    protected function getProductQuery(): Builder
    {
        $query = parent::getProductQuery();

        $supplierQuery = Supplier::select('id', 'name');
        foreach ($this->supplierRates as $supplier => $rate) {
            $supplierQuery->orWhere('name', 'LIKE', '%' . $supplier . '%');
        }
        $suppliers = $supplierQuery->get();

        $brands = [];
        foreach ($suppliers as $supplier) {
            $brands = array_merge($brands, $supplier->brands->pluck('id')->all());
        }

        return $query
            ->where('exclude_bol_export', '!=', '1')
            ->whereIn('brand_id', $brands)
            ->whereNotNull('ean');
    }

    protected function productToFeedRow(Product $product)
    {
        // fetch images
        $images = implode(',', $product->images->map->getPublicUrl(true)->all());

        // set bol price
        $shippingCost = 0;

        $priceAsMoney = optional($product->getCurrentPrice())->priceAsMoney();
        $price = $priceAsMoney ? MoneyFactory::toFloat($priceAsMoney) : 0;

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

        $bolPrice = $price + $shippingCost;

        $fields = [
            'Productnaam' => $product->name,
            'Korte Omschrijving' => $product->short_description,
            'Omschrijving' => $product->description,
            'Levertijd' => $product->shippingTime->name ?? $this->getDelivery($product),
            'Images' => $images,
            'EAN' => $product->ean,
            'BOL prijs' => $bolPrice,
            'Commissie' => '',
        ];

        return $fields;
    }
}
