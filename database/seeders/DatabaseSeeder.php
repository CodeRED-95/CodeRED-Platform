<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Administrador', 'description' => 'Acceso total', 'is_system' => true]
        );

        $admin = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrador', 'description' => 'Administración operativa', 'is_system' => true]
        );

        $permissions = collect([
            ['slug' => 'dashboard.view', 'name' => 'Ver dashboard'],
            ['slug' => 'agencies.view', 'name' => 'Ver agencias'],
            ['slug' => 'agencies.manage', 'name' => 'Gestionar agencias'],
            ['slug' => 'agencies.create', 'name' => 'Crear agencias'],
            ['slug' => 'agencies.update', 'name' => 'Editar agencias'],
            ['slug' => 'agencies.delete', 'name' => 'Eliminar agencias'],
            ['slug' => 'agencies.restore', 'name' => 'Restaurar agencias'],
            ['slug' => 'agencies.import', 'name' => 'Importar agencias'],
            ['slug' => 'agencies.export', 'name' => 'Exportar agencias'],
            ['slug' => 'agencies.view_history', 'name' => 'Ver historial de agencias'],
            ['slug' => 'agencies.manage_status', 'name' => 'Gestionar estado de agencias'],
        ])->map(fn (array $item) => Permission::query()->firstOrCreate($item, ['description' => null]));

        $superAdmin->permissions()->sync($permissions->pluck('id')->all());
        $admin->permissions()->sync(
            $permissions->whereNotIn('slug', ['agencies.delete'])->pluck('id')->all()
        );

        $devUser = User::query()->firstOrCreate(
            ['email' => env('DEV_ADMIN_EMAIL')],
            [
                'name' => env('DEV_ADMIN_NAME', 'Administrador Dev'),
                'password' => Hash::make((string) env('DEV_ADMIN_PASSWORD')),
                'is_active' => true,
            ]
        );

        $devUser->roles()->syncWithoutDetaching([$superAdmin->id, $admin->id]);

        Agency::factory()->count(25)->create();
    }
}
