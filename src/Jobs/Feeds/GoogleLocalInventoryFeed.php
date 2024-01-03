<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Illuminate\Database\Eloquent\Builder;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\ProductAttribute\ProductAttribute;

class GoogleLocalInventoryFeed extends Feed
{
    /** @var string */
    public $type = 'txt';

    /** @var string */
    public $vendor = 'google-local-inventory';

    /** @var string[] */
    public $fieldNames = [
        'store_code',
        'id',
        'quantity',
        'availability',
        'pickup_method',
//        'pickup_sla'
    ];

    protected function getProductQuery(): Builder
    {
        $query = parent::getProductQuery();

        return $query
            ->whereNotIn('productattributeset_id', $this->excludedGoogleAttributeSets)
            ->whereNull('deleted_at')
            ->whereHas('productproperties', function ($query) {
                $query
                    ->join(ProductAttribute::table(), 'productattribute_id', '=', ProductAttribute::table() . '.id')
                    ->where('code', 'include-in-google-feed')
                    ->where('value', '1');
            });
    }

    /**
     * @param Product $product
     * @return array|array[]
     */
    protected function productToFeedRow(Product $product): array
    {
        $isForSale = ($product->stock->sum('in_stock') <= 0 && !$product->is_procured_on_demand) ? false : $product->is_for_sale;

        $mainGoogleStoreRow = [
            'store_code' => config('daalder-feeds.main-google-store.store-code'),
            'id' => $product->id,
            'quantity' => !$isForSale ? 0 : $product->stock->sum('in_stock'),
            'availability' => $isForSale ? 'in_stock' : 'out_of_stock',
            'pickup_method' => 'buy',
//            'pickup_sla' => ''
        ];

        $additionalStoresCount = $this->getSecondaryBusinessLocationsCount();

        if ($additionalStoresCount) {
            $rows = [$mainGoogleStoreRow];
            foreach (range(1, $additionalStoresCount) as $pickupPointNumber) {
                $rows[] = [
                    'store_code' => $pickupPointNumber,
                    'id' => $product->id,
                    'quantity' => !$isForSale ? 0 : 1,
                    'availability' => $isForSale ? 'on_display_to_order' : 'out_of_stock',
                    'pickup_method' => 'ship to store',
//                        'pickup_sla' => ''
                ];
            }

            return $rows;
        }

        return $mainGoogleStoreRow;
    }

    /**
     * @param $query
     * @return int
     */
    public function getProductsCount($query)
    {
        $multiplier = $this->getSecondaryBusinessLocationsCount() + 1;

        return $query->count() * $multiplier;
    }

    /**
     * @return int
     */
    private function getSecondaryBusinessLocationsCount(): int
    {
        if (config('daalder-feeds.main-google-store.main-pickup-point-id')) {
            return $this->store->pickupPoints
                ->where('id', '!=', config('daalder-feeds.main-google-store.main-pickup-point-id'))
                ->count();
        }

        return 0;
    }
}
