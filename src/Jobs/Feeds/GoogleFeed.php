<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Daalder\Feeds\Services\VariationChecker;
use Illuminate\Database\Eloquent\Builder;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\ProductAttribute\Option;
use Pionect\Daalder\Models\ProductAttribute\ProductAttribute;
use Pionect\Daalder\Services\MoneyFactory;

class GoogleFeed extends Feed
{
    /** @var string */
    public $type = 'txt';

    /** @var string */
    public $vendor = 'google';

    /** @var string[] */
    public $fieldNames = [
        'id',
        'title',
        'description',
        'link',
        'image_link',
        'price',
        'sale_price',
        'cost_of_goods_sold',
        'currency',
        'availability',
        'shipping(country:region:postal_code:price:max_handling_time:max_transit_time)',
        'shipping_label',
        'expiration_date',
        'condition',
        'product_type',
        'google_product_category',
        'item_group_id',
        'payment_accepted',
        'gtin',
        'brand',
        'mpn',
        'custom_label_0',
        'custom_label_1',
        'custom_label_2',
        'custom_label_3',
        'custom_label_4',
    ];

    protected function getProductQuery(): Builder
    {
        $query = parent::getProductQuery();

        return $query
            ->whereNotIn('productattributeset_id', $this->excludedGoogleAttributeSets)
            ->whereNull('deleted_at')
            ->whereHas('productproperties', function ($query) {
                $query
                    ->join(ProductAttribute::table(), 'productattribute_id', '=', ProductAttribute::table().'.id')
                    ->where('code', 'include-in-google-feed')
                    ->where('value', '1');
            })
            ->has('shippingTimes');
    }

    protected function productToFeedRow(Product $product)
    {
        $priceObject = $product->getCurrentPrice();
        $currency = $this->priceFormatter->getCurrency($product);
        /** @var VariationChecker $variationChecker */
        $variationChecker = app(VariationChecker::class);

        $isForSale = ($product->stock->sum('in_stock') <= 0 && ! $product->is_procured_on_demand) ? false : $product->is_for_sale;

        $fields = [
            'id' => $product->id,
            'title' => $product->name,
            'description' => $product->description,
            'link' => $this->getHost().'/'.$product->url,
            'image_link' => '', //Filled below
            'price' => $this->priceFormatter->getFormattedPrice($product),
            'sale_price' => '', //Filled below,
            'cost_of_goods_sold' => $product->cost_price ? $product->cost_price.' '.$currency : '',
            'currency' => $currency,
            'availability' => $isForSale ? 'in_stock' : 'out_of_stock',
            'shipping(country:region:postal_code:price:max_handling_time:max_transit_time)' => $this->getShippingValues($product, $currency),
            'shipping_label' => $product->shippingTier ? $product->shippingTier->id : '',
            'expiration_date' => now()->addWeeks(2)->toDateString(),
            'condition' => 'new',
            'product_type' => ($product->group_id !== null) ? $product->group->name : '(not set)',
            'google_product_category' => '',
            'item_group_id' => $variationChecker->getVariationGroupString($product),
            'payment_accepted' => 'IDEAL,Paypal,BanContact Mister Cash,Bankoverschrijving',
            'gtin' => $product->ean,
            'brand' => '', //Filled below
            'mpn' => $product->sku,
            'custom_label_0' => '',
            'custom_label_1' => '',
            'custom_label_2' => $this->getGrossMargin($product), // Gross margin
            'custom_label_3' => 'non-drop', //Dropship products Nubuiten (see function below),
            'custom_label_4' => $this->getTag($product), // First G: tag.
        ];

        if ($product->shippingTime) {
            if ($product->shippingTime->id == 10) {
                $fields['custom_label_3'] = 'drop';
            }
        }

        if (optional($priceObject)->list_price && optional($priceObject)->list_price != 0) {
            // TODO: remove temporary fix for daalder ~13.15.5
            if (optional($priceObject)->list_price != optional($priceObject)->price) {
                $fields['price'] = $this->priceFormatter->getFormattedListPrice($product);
                $fields['sale_price'] = $this->priceFormatter->getFormattedPrice($product);
            }
        }

        $googleProductCategoryProperty = $product->getProperty('google-product-category');
        if ($googleProductCategoryProperty) {
            $googleProductCode = optional(Option::find($googleProductCategoryProperty->pivot->value))->code;
            $fields['google_product_category'] = $googleProductCode ?: '';
        }

        if (! is_null($product->brand)) {
            $fields['brand'] = $product->brand->name;
        }

        $image = $product->images()
            ->first();

        if (! is_null($image)) {
            $fields['image_link'] = $image->src;
        }

        return $fields;
    }

    /**
     * @return string
     */
    protected function getTag(Product $product): ?string
    {
        $tag = $product->tags()->where('name', 'like', 'G:%')->first();

        return ($tag) ? $tag->name : '';
    }

    private function getShippingValues(Product $product, string $currency): string
    {
        $countryCode = $this->priceFormatter->getCountryCode();
        $shippingMethod = null;
        $price = '';

        if ($product->shippingTier && $product->shippingTier->methods) {
            $shippingMethod = $product->shippingTier->methods->where('country_code', $countryCode)->first();
        }

        if ($shippingMethod && $shippingMethod->price) {
            $price = sprintf(
                '%s %s',
                MoneyFactory::toString($shippingMethod->price),
                $currency
            );
        }

        $attributes = [
            'country' => $countryCode,
            'region' => '',
            'service' => '',
            'price' => $price,
            'max_handling_time' => 0,
            'max_transit_time' => $product->shippingTime->days,
        ];

        return implode(':', array_values($attributes));
    }
}
