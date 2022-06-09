<?php

use Illuminate\Database\Migrations\Migration;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Product\ProductProductProperty;
use Pionect\Daalder\Models\Product\ProductProperty;
use Pionect\Daalder\Models\ProductAttribute\Group;
use Pionect\Daalder\Models\ProductAttribute\ProductAttribute;
use Pionect\Daalder\Models\ProductAttribute\Repositories\ProductAttributeRepository;

return new class extends Migration {
    /** @var ProductAttributeRepository $productAttributeRepository */
    private $productAttributeRepository;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->productAttributeRepository = app(ProductAttributeRepository::class);

        // Get all locales on this installation
        $locales = [];
        foreach (config('translatable.locales') as $language => $countries) {
            foreach ($countries as $country) {
                $locales[] = $language.config('translatable.locale_separator').$country;
            }
        }

        /** @var Group $group */
        $group = Group::updateOrCreate(['code' => 'feeds'], [
            'icon' => 'leak_add',
            'sort_order' => 110,
        ]);

        $facebookAttribute = $this->createAttributeAndProperties($locales, $group, 'include-in-facebook-feed', 1);
        $googleAttribute = $this->createAttributeAndProperties($locales, $group, 'include-in-google-feed', 1);
        $netrivalsAttribute = $this->createAttributeAndProperties($locales, $group, 'include-in-netrivals-feed', 0);

        // Set default values foreach possible translation
        foreach($locales as $locale) {
            $facebookAttribute->translateOrNew($locale)->name = 'Include in Facebook Feed';
            $facebookAttribute->translateOrNew($locale)->description = 'Whether or not to include this product in the Facebook Feed.';
            $facebookAttribute->translateOrNew($locale)->default_value = 1;
            $facebookAttribute->save();

            $googleAttribute->translateOrNew($locale)->name = 'Include in Google Feed';
            $googleAttribute->translateOrNew($locale)->description = 'Whether or not to include this product in the Google Feed.';
            $googleAttribute->translateOrNew($locale)->default_value = 1;
            $googleAttribute->save();

            $netrivalsAttribute->translateOrNew($locale)->name = 'Include in Netrivals Feed';
            $netrivalsAttribute->translateOrNew($locale)->description = 'Whether or not to include this product in the Netrivals Feed.';
            $netrivalsAttribute->translateOrNew($locale)->default_value = 0;
            $netrivalsAttribute->save();
        }
    }

    private function createAttributeAndProperties(array $locales, Group $group, string $code, int $defaultValue) {
        /** @var ProductAttribute $attribute */
        $attribute = $this->productAttributeRepository->store([
            'productattributegroup_id' => $group->id,
            'code' => $code,
            'inputtype' => 'boolean',
            'is_global' => 1,
        ]);

        // Get the global ProductProperty belonging to the new ProductAttribute
        $property = ProductProperty::query()->globals()->firstWhere('productattribute_id', $attribute->id);
        $productIdsChunks = Product::query()->pluck('id')->chunk(100);

        // Chunk through the product ids
        foreach ($productIdsChunks as $productIdsChunk) {
            // Generate insertable data for the ProductProductProperty pivot model
            $productProductPropertiesData = $productIdsChunk->map(function ($id) use ($property, $locales, $defaultValue) {
                $data = [];

                foreach($locales as $locale) {
                    $data[] = [
                        'value' => $defaultValue,
                        'locale' => $locale,
                        'product_id' => $id,
                        'productproperty_id' => $property->id,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ];
                }

                return $data;
            })->flatten(1);

            // Insert chunk of data
            ProductProductProperty::query()->insert($productProductPropertiesData->toArray());
        }

        return $attribute;
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
};
