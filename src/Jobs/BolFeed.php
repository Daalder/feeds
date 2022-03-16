<?php

namespace App\Jobs\Feeds;

use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Supplier\Supplier;
use Pionect\Daalder\Services\MoneyFactory;

class BolFeed extends Feed
{
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

    protected function generate()
    {
        $supplierRates = [
            'KBT' => 9.5,
            'Westwood' => 8.5,
            'Woodvision' => 8.5,
            'Wienerberger' => 8.5,
        ];

        $supplierQuery = Supplier::select('id', 'name');
        foreach ($supplierRates as $supplier => $rate) {
            $supplierQuery->orWhere('name', 'LIKE', '%'.$supplier.'%');
        }
        $suppliers = $supplierQuery->get();
        $brands = [];
        foreach ($suppliers as $supplier) {
            $brands = array_merge($brands, $supplier->brands->pluck('id')->all());
        }

        $query = $this->productRepository->newQuery()
            ->where('exclude_bol_export', '!=', '1')
            ->whereIn('brand_id', $brands)
            ->whereNotNull('ean');

        $this->generateFeed(
            'csv',
            'bol',
            function (Product $product) use ($suppliers, $supplierRates) {
                // fetch images
                $images = implode(',', $product->images()
                    ->pluck('src')->all());

                // set bol price
                $shippingCost = 0;

                $priceObject = $product->getCurrentPrice();
                $price = $priceObject->priceAsMoney();
                $price = $price ? MoneyFactory::toFloat($price) : 0;

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
                ];

                return $fields;
            },
            $query
        );
    }
}
