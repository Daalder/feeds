<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add Wizard permissions
        Artisan::call('db:seed', [
            '--class' => 'FeedsPermissionsTableSeeder',
            '--force' => true // Required on production environments
        ]);

        Artisan::call('db:seed', [
            '--class' => 'AssignFeedsPermissionsToRolesSeeder',
            '--force' => true // Required on production environments
        ]);
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

