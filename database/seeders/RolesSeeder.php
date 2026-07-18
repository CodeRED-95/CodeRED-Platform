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

        Role::query()->updateOrCreate(
            ['slug' => 'editor'],
            ['name' => 'Editor', 'description' => 'Edición limitada', 'is_system' => false]
        );

        Role::query()->updateOrCreate(
            ['slug' => 'viewer'],
            ['name' => 'Consulta', 'description' => 'Acceso de lectura', 'is_system' => false]
        );
    }
}
