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
        $editorPermissionIds = Permission::query()->whereIn('slug', [
            'dashboard.view',
            'agencies.view',
            'agencies.create',
            'agencies.update',
            'agencies.manage_status',
        ])->pluck('id')->all();
        $viewerPermissionIds = Permission::query()->where('slug', 'agencies.view')->pluck('id')->all();

        Role::query()->where('slug', 'super-admin')->first()?->permissions()->sync($allPermissionIds);
        Role::query()->where('slug', 'editor')->first()?->permissions()->sync($editorPermissionIds);
        Role::query()->where('slug', 'viewer')->first()?->permissions()->sync($viewerPermissionIds);
    }
}
