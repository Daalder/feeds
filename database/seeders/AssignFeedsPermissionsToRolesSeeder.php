<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Daalder\Feeds\Models\Feed\FeedsPermission;
use Pionect\Daalder\Models\User\Role;

class AssignFeedsPermissionsToRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = FeedsPermission::query()->get();

        Role::admin()->get()->each(function (Role $role) use ($permissions) {
            foreach ($permissions as $permission) {
                if (!$role->permissions()->where('name', $permission->name)->exists() && $role->guard_name == $permission->guard_name) {
                    $role->permissions()->save($permission);
                }
            }
        });


    }
}
