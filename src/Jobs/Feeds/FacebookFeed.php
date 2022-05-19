<?php

namespace App\Jobs\Feeds;

namespace Daalder\Feeds\Jobs\Feeds;
use Illuminate\Database\Eloquent\Builder;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\ProductAttribute\ProductAttribute;
use Pionect\Daalder\Models\Shipping\ShippingMethod;
use Pionect\Daalder\Services\MoneyFactory;

/**
 * @see https://www.facebook.com/business/help/120325381656392?id=725943027795860
 * Class FacebookFeed
 */
class FacebookFeed extends Feed
{
    /** @var string */
    public $type = 'csv';

    /** @var string */
    public $vendor = 'facebook';

    /** @var string[] */
    public $fieldNames = [
        'id',
        'title',
        'description',
        'link',
        'image_link',
        'price',
        'sale_price',
        'availability',
        'shipping',
        'expiration_date',
        'condition',
        'product_type',
        'google_product_category',
        'gtin',
        'brand',
        'mpn',
        'custom_label_0',
        'custom_label_1',
        'custom_label_2',
        'custom_label_3',
        'custom_label_4',
    ];

    protected function getProductQuery() {
        $query = parent::getProductQuery();

        return $query
            ->whereNotIn('productattributeset_id', $this->excludedGoogleAttributeSets)
            ->where('is_for_sale', '!=', 0)
            ->whereNull('deleted_at')
            ->whereHas('productproperties', function ($query) {
                $query
                    ->join(ProductAttribute::table(), 'productattribute_id', '=', ProductAttribute::table().'.id')
                    ->where('code', 'include-in-facebook-feed')
                    ->where('value', '1');
            });;
    }

    protected function productToFeedRow(Product $product) {

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

        $shippingTime = null;

        if ($product->shippingTime) {
            if ($product->shippingTime->id == 10) {
                $shippingTime = 1;
            }
        }

        $fields = [
            'id' => $product->sku,
            'title' => $product->name,
            'description' => $product->description,
            'link' => $this->getHost().'/'.$product->url,
            'image_link' => $this->getImageLink($product),
            'price' => $this->getFormattedPrice($product),
            'sale_price' => '', //Filled below,
            'availability' => 'in stock',
            'shipping' => $shipping,
            'expiration_date' => now()->addWeeks(2)->toDateString(),
            'condition' => 'new',
            'product_type' => $this->getCategories($product), //The category
            'google_product_category' => $product->productattributeset ? $product->productattributeset->name : null,
            'gtin' => $product->ean,
            'brand' => optional($product->brand)->name,
            'mpn' => $product->sku,
            'custom_label_0' => $this->margeMapper($product->marge),            // Marge
            'custom_label_1' => ($product->isDropShipped() == false) ? 1 : 2,   // Product is dropshipped
            'custom_label_2' => $this->getInStock($product),                    // Stock count
            'custom_label_3' => $shippingTime,                                  // Order before tuesday, delivered the same week
            'custom_label_4' => $this->getTag($product),                        // First tag that starts with G:
        ];

        if (optional($priceObject)->list_price && optional($priceObject)->list_price != 0) {
            // Temporary check for daalder ~13.5.5
            if(optional($priceObject)->list_price != optional($priceObject)->price) {
                $fields['price'] = $this->getFormattedListPrice($product);
                $fields['sale_price'] = $this->getFormattedPrice($product);
            }
        }

        return $fields;
    }
}
