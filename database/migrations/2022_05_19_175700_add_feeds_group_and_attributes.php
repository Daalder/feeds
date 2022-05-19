<?php

use Illuminate\Database\Migrations\Migration;
use Pionect\Daalder\Models\ProductAttribute\Group;
use Pionect\Daalder\Models\ProductAttribute\ProductAttribute;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /** @var Group $group */
       $group = Group::updateOrCreate(['code' => 'feeds'], [
           'icon' => 'leak_add',
           'sort_order' => 110,
       ]);

       /** @var ProductAttribute $includeGoogleFeed */
       $includeFacebookFeed = ProductAttribute::updateOrCreate(['code' => 'include-in-facebook-feed'], [
           'productattributegroup_id' => $group->id,
           'inputtype' => 'boolean',
           'is_global' => 1,
       ]);

        /** @var ProductAttribute $includeGoogleFeed */
        $includeGoogleFeed = ProductAttribute::updateOrCreate(['code' => 'include-in-google-feed'], [
            'productattributegroup_id' => $group->id,
            'inputtype' => 'boolean',
            'is_global' => 1,
        ]);

        /** @var ProductAttribute $includeGoogleFeed */
        $includeNetrivalsFeed = ProductAttribute::updateOrCreate(['code' => 'include-in-netrivals-feed'], [
            'productattributegroup_id' => $group->id,
            'inputtype' => 'boolean',
            'is_global' => 1,
        ]);

        // Set default values foreach possible translation
        foreach(config('translatable.locales') as $languageCode => $countryCodes) {
            foreach($countryCodes as $countryCode) {
                $includeFacebookFeed->translateOrNew($languageCode.'_'.$countryCode)->name = 'Include in Facebook Feed';
                $includeFacebookFeed->translateOrNew($languageCode.'_'.$countryCode)->description = 'Whether or not to include this product in the Facebook Feed.';
                $includeFacebookFeed->translateOrNew($languageCode.'_'.$countryCode)->default_value = 1;
                $includeFacebookFeed->save();

                $includeGoogleFeed->translateOrNew($languageCode.'_'.$countryCode)->name = 'Include in Google Feed';
                $includeGoogleFeed->translateOrNew($languageCode.'_'.$countryCode)->description = 'Whether or not to include this product in the Google Feed.';
                $includeGoogleFeed->translateOrNew($languageCode.'_'.$countryCode)->default_value = 1;
                $includeGoogleFeed->save();

                $includeNetrivalsFeed->translateOrNew($languageCode.'_'.$countryCode)->name = 'Include in Netrivals Feed';
                $includeNetrivalsFeed->translateOrNew($languageCode.'_'.$countryCode)->description = 'Whether or not to include this product in the Netrivals Feed.';
                $includeNetrivalsFeed->translateOrNew($languageCode.'_'.$countryCode)->default_value = 1;
                $includeNetrivalsFeed->save();
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
