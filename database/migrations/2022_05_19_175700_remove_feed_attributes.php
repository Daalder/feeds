<?php

use Illuminate\Database\Migrations\Migration;
use Pionect\Daalder\Models\Product\ProductProductProperty;
use Pionect\Daalder\Models\Product\ProductProperty;
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
        $this->removeAttribute('include-in-facebook-feed');
        $this->removeAttribute('include-in-google-feed');
        $this->removeAttribute('include-in-netrivals-feed');
    }

    private function removeAttribute(string $code) {
        /** @var ProductAttribute $attribute */
        $attribute = ProductAttribute::firstWhere('code', $code);
        $property = ProductProperty::query()->globals()->firstWhere('productattribute_id', $attribute->id);;

        if($property) {
            ProductProductProperty::where('productproperty_id', $property->id)->forceDelete();
            $property->forceDelete();
        }

        $attribute->forceDelete();
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
