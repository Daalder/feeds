<?php

use Illuminate\Database\Migrations\Migration;
use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Product\ProductProductProperty;
use Pionect\Daalder\Models\Product\ProductProperty;
use Pionect\Daalder\Models\ProductAttribute\Group;
use Pionect\Daalder\Models\ProductAttribute\ProductAttribute;
use Pionect\Daalder\Models\ProductAttribute\Repositories\ProductAttributeRepository;
use Pionect\Daalder\Models\ProductAttribute\Option;
use Illuminate\Support\Facades\File;

return new class extends Migration {
    /** @var ProductAttributeRepository $productAttributeRepository */
    private $productAttributeRepository;

    private $googleProductCategoriesListPath = 'vendor/daalder/feeds/storage/GoogleProductCategory/';

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
        $group = Group::firstWhere('code', 'feeds');

        $googleProductCategory = $this->productAttributeRepository->store([
            'productattributegroup_id' => $group->id,
            'code' => 'google-product-category',
            'inputtype' => 'select',
            'is_global' => 1,
        ]);
        
        // Set default values foreach possible translation
        foreach($locales as $locale) {
            // Translate attribute
            $googleProductCategory->translateOrNew($locale)->name = 'Google Product Category';
            $googleProductCategory->translateOrNew($locale)->description = 'Product Category from Google\'s product taxonomy' ;
            $googleProductCategory->save();

            if(File::exists(base_path($this->googleProductCategoriesListPath.$locale))) {
                $file = File::files(base_path($this->googleProductCategoriesListPath.$locale))[0];
                if(!$file) {
                    continue;
                }
                $openedFile = fopen($file, 'r');
                while(!feof($openedFile)) {
                    $line = fgets($openedFile);
                    // Ignore line with comment
                    if(str_starts_with($line, '#')) {
                      continue;  
                    }
                    // Ignore empty line
                    if(!str_contains($line, '-')) {
                        continue;
                    }
                    [$code, $translation] = explode('-', $line, 2);
                    $categoryParts = explode('>', $translation);
                    $category = trim(array_pop($categoryParts));
                    if(count($categoryParts)) {
                        $category .= ' ('.trim(array_pop($categoryParts)).')';
                    }
                    $option = Option::firstOrNew(['code' => trim($code), 'productattribute_id' => $googleProductCategory->id]);
                    $option->translateOrNew($locale)->value = $category;
                    $option->save();
                }
            }
        }
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
