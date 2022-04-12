<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Illuminate\Database\Eloquent\Builder;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Product\ProductProductProperty;
use Pionect\Daalder\Models\ProductAttribute\ProductAttribute;
use Pionect\Daalder\Models\Shipping\Rate;
use Pionect\Daalder\Models\Shipping\ShippingMethod;
use Pionect\Daalder\Services\MoneyFactory;

class NetrivalsFeed extends Feed
{
    /** @var string */
    public $type = 'csv';

    /** @var string */
    public $vendor = 'netrivals';

    /** @var string[] */
    public $fieldNames = [
        'product_id',
        'title',
        'price',
        'image_url',
        'product_type',
        'brand',
        'ean',
        'mpn',
        'shipping_costs',
        'cost_price',
        'vat',
        'item_group_id',
        'availability',
        'quantity',
        'description',
    ];

    protected function getProductQuery() {
        $query = parent::getProductQuery();

        return $query
            ->with('vatRates', 'stock')
            ->whereNull('deleted_at')
            ->whereNotNull('ean')
            ->whereHas('productproperties', function($query) {
                $query
                    ->join(ProductAttribute::table(), 'productattribute_id', '=', ProductAttribute::table().'.id')
                    ->where('code', 'netrivals-prijsvergelijk')
                    ->where('value', '1');
            });
    }

    protected function productToFeedRow(Product $product)
    {
        $categories = $product->feedCategories()
            ->whereNull('feed_consumer_id')
            ->orderBy('feed_category_product.id')
            ->get();

        $fields = [
            'product_id' => $product->id,
            'title' => $product->name,
            'price' => $this->getFormattedPrice($product),
            'image_url' => optional($product->images()->first())->src,
            'product_type' => ($product->group_id !== null) ? $product->group->name : '(not set)',
            'brand' => optional($product->brand)->name,
            'ean' => $product->ean,
            'mpn' => $product->sku,
            'shipping_costs' => optional(optional($product->shippingMethods()->first())->price)->getAmount() / 100,
            'cost_price' => $product->cost_price,
            'vat' => (int) $product->getActiveVatRate()->percentage,
            'item_group_id' => $product->group_id,
            'availability' => ($product->is_for_sale == 1) ? 'in stock' : 'out of stock',
            'quantity' => ($product->stock->count() > 0) ? $product->stock->sum('in_stock') : 0,
            'description' => $product->description,
            'tags' => '',
        ];

        return $fields;
    }
}
