<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            OrgSeeder::class,
            SecretariatSeeder::class,
        ]);

        if (app()->isLocal()) {
            $this->call(DemoSeeder::class);
        }
    }
}
