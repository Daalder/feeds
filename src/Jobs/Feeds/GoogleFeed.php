<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Illuminate\Database\Eloquent\Builder;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Shipping\ShippingMethod;
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
        'currency',
        'availability',
        'shipping',
        'shipping_label',
        'expiration_date',
        'condition',
        'product_type',
//        'google_product_category',
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

    protected function productToFeedRow(Product $product)
    {
        if (in_array($product->productattributeset_id, $this->excludedGoogleAttributeSets) || !is_null($product->deleted_at)) {
            return false;
        }

        $includeInGoogleFeed = optional(optional($product->getProperty('include-in-google-feed'))->pivot)->value;

        if (!is_null($includeInGoogleFeed) && $includeInGoogleFeed == false) {
            return false;
        }
        
        $priceObject = $product->getCurrentPrice();
        $currency = $this->getCurrency($product);
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

        $fields = [
            'id' => $product->id,
            'title' => $product->name,
            'description' => $product->description,
            'link' => $this->getHost().'/'.$product->url,
            'image_link' => '', //Filled below
            'price' => $this->getFormattedPrice($product),
            'sale_price' => '', //Filled below
            'currency' => $currency,
            'availability' => ($product->is_for_sale == 1) ? 'in_stock' : 'out_of_stock',
            'shipping' => '',
            'shipping_label' => $product->shippingTier ? $product->shippingTier->id : '',
            'expiration_date' => now()->addWeeks(2)->toDateString(),
            'condition' => 'new',
            'product_type' => ($product->group_id !== null) ? $product->group->name : '(not set)',
//            'google_product_category' => $product->productattributeset ? $product->productattributeset->name : null,
            'payment_accepted' => 'IDEAL,Paypal,BanContact Mister Cash,Bankoverschrijving',
            'gtin' => $product->ean,
            'brand' => '', //Filled below
            'mpn' => $product->sku,
            'custom_label_0' => $this->margeMapper($product->marge),             // Marge
            'custom_label_1' => ($product->isDropShipped() == false) ? 1 : 2,    // Product is dropship
            'custom_label_2' => ($product->stock->count() > 0) ? $product->stock->sum('in_stock') : 0,
            // Nubuiten lokale voorraad absolute aantallen
            'custom_label_3' => '',
            // Voor dinsdag besteld zelfde week in huis producten
            'custom_label_4' => $this->getTag($product),                          // First G: tag.
        ];

        if ($product->shippingTime) {
            if ($product->shippingTime->id == 10) {
                $fields['custom_label_3'] = 1;
            }
        }

        $property = $product->productproperties()->whereHas('productattribute', function (Builder $query) {
            $query->where('code', 'gaatuitvoorraad');
        })->first();

        if ($property) {
            $fields['custom_label_2'] = $property->pivot->value;
        } else {
            $fields['custom_label_2'] = 0;
        }

        if (optional($priceObject)->list_price && optional($priceObject)->list_price != 0) {
            // TODO: remove temporary fix for daalder ~13.15.5
            if (optional($priceObject)->list_price != optional($priceObject)->price) {
                $fields['price'] = $this->getFormattedListPrice($product);
                $fields['sale_price'] = $this->getFormattedPrice($product);
            }
        }

        if (!is_null($product->brand)) {
            $fields['brand'] = $product->brand->name;
        }

        $image = $product->images()
            ->first();

        if (!is_null($image)) {
            $fields['image_link'] = $image->src;
        }

        return $fields;
    }

    /**
     * @return string
     */
    protected function getTag(Product $product)
    {
        $tag = $product->tags()->where('name', 'like', 'G:%')->first();

        return ($tag) ? $tag->name : '';
    }
}
