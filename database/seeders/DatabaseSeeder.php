<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UbigeoSeeder::class);
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminSeeder::class,
            SettingsSeeder::class,
            AgencySeeder::class,
        ]);
    }
}
