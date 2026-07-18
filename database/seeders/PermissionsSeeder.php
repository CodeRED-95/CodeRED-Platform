<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        collect([
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
            ['slug' => 'users.view', 'name' => 'Ver usuarios'],
            ['slug' => 'users.create', 'name' => 'Crear usuarios'],
            ['slug' => 'users.update', 'name' => 'Editar usuarios'],
            ['slug' => 'users.delete', 'name' => 'Eliminar usuarios'],
            ['slug' => 'users.restore', 'name' => 'Restaurar usuarios'],
            ['slug' => 'users.manage_roles', 'name' => 'Gestionar roles de usuarios'],
            ['slug' => 'users.reset_password', 'name' => 'Restablecer contraseñas'],
            ['slug' => 'users.manage_status', 'name' => 'Gestionar estado de usuarios'],
            ['slug' => 'users.view_activity', 'name' => 'Ver actividad de usuarios'],
        ])->each(fn (array $item) => Permission::query()->updateOrCreate(
            ['slug' => $item['slug']],
            ['name' => $item['name'], 'description' => null]
        ));

        $allPermissionIds = Permission::query()->pluck('id')->all();
        $operationalPermissionIds = Permission::query()
            ->whereIn('slug', [
                'dashboard.view',
                'agencies.view',
                'agencies.manage',
                'agencies.create',
                'agencies.update',
                'agencies.delete',
                'agencies.restore',
                'agencies.import',
                'agencies.export',
                'agencies.view_history',
                'agencies.manage_status',
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                'users.restore',
                'users.manage_roles',
                'users.reset_password',
                'users.manage_status',
                'users.view_activity',
            ])
            ->pluck('id')
            ->all();

        Role::query()->where('slug', 'super-admin')->first()?->permissions()->syncWithoutDetaching($allPermissionIds);
        Role::query()->where('slug', 'admin')->first()?->permissions()->syncWithoutDetaching($operationalPermissionIds);
    }
}
