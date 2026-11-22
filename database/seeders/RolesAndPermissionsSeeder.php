<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Support\Authorization\PermissionMatrix;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: safe to re-run after the matrix changes.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(PermissionMatrix::permissions()) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (PermissionMatrix::matrix() as $role => $permissions) {
            RoleModel::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        // Every enum role must exist even if it has no permissions yet.
        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
