<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        Role::query()->updateOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Administrador', 'description' => 'Acceso total', 'is_system' => true]
        );

        Role::query()->updateOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrador', 'description' => 'Administración operativa', 'is_system' => true]
        );
    }
}
