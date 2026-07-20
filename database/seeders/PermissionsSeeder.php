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
            ['slug' => 'settings.dni.view', 'name' => 'Ver ajustes DNI'],
            ['slug' => 'settings.dni.update', 'name' => 'Actualizar ajustes DNI'],
            ['slug' => 'settings.dni.test', 'name' => 'Probar proveedor DNI'],
            ['slug' => 'settings.dni.clear-cache', 'name' => 'Limpiar caché DNI'],
            ['slug' => 'dni-records.view', 'name' => 'Ver registros DNI'],
            ['slug' => 'dni-records.create', 'name' => 'Crear registros DNI'],
            ['slug' => 'dni-records.update', 'name' => 'Actualizar registros DNI'],
            ['slug' => 'dni-records.delete', 'name' => 'Eliminar registros DNI'],
            ['slug' => 'dni-records.refresh', 'name' => 'Actualizar desde proveedor DNI'],
            ['slug' => 'api-tokens.view-own', 'name' => 'Ver tokens propios'],
            ['slug' => 'api-tokens.create-own', 'name' => 'Crear tokens propios'],
            ['slug' => 'api-tokens.revoke-own', 'name' => 'Revocar tokens propios'],
            ['slug' => 'api-tokens.view-any', 'name' => 'Ver todos los tokens'],
            ['slug' => 'api-tokens.create-for-users', 'name' => 'Crear tokens para usuarios'],
            ['slug' => 'api-tokens.revoke-any', 'name' => 'Revocar cualquier token'],
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
