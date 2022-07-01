<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Daalder\Feeds\Models\Feed\FeedsPermission;

class FeedsPermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = collect();
        foreach (FeedsPermission::constants() as $role) {
            foreach (['web', 'api'] as $guard) {
                $permissions->push(FeedsPermission::firstOrCreate([
                    'name' => $role,
                    'guard_name' => $guard
                ]));
            }
        }
    }
}
