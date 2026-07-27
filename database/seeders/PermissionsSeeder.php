<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
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
                ['slug' => 'agencies.backup.view', 'name' => 'Ver copias de agencias'],
                ['slug' => 'agencies.backup.create', 'name' => 'Crear copias de agencias'],
                ['slug' => 'agencies.backup.download', 'name' => 'Descargar copias de agencias'],
                ['slug' => 'agencies.backup.delete', 'name' => 'Eliminar copias de agencias'],
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
                ['slug' => 'api-tools.dni.test', 'name' => 'Probar API DNI'],
                ['slug' => 'settings.api-documentation.update', 'name' => 'Configurar documentación API'],
                ['slug' => 'settings.agency-backups.update', 'name' => 'Configurar copias de agencias'],
                ['slug' => 'settings.ubigeos.update', 'name' => 'Sincronizar catálogo de UBIGEO'],
                ['slug' => 'settings.dni.view', 'name' => 'Ver ajustes DNI'],
                ['slug' => 'settings.dni.update', 'name' => 'Actualizar ajustes DNI'],
                ['slug' => 'settings.dni.test', 'name' => 'Probar proveedor DNI'],
                ['slug' => 'settings.dni.clear-cache', 'name' => 'Limpiar caché DNI'],
                ['slug' => 'dni-records.view', 'name' => 'Ver registros DNI'],
                ['slug' => 'dni-records.create', 'name' => 'Crear registros DNI'],
                ['slug' => 'dni-records.update', 'name' => 'Actualizar registros DNI'],
                ['slug' => 'dni-records.delete', 'name' => 'Eliminar registros DNI'],
                ['slug' => 'dni-records.refresh', 'name' => 'Actualizar desde proveedor DNI'],
                ['slug' => 'ruc.view', 'name' => 'Ver padrón RUC'],
                ['slug' => 'ruc.test', 'name' => 'Probar API RUC'],
                ['slug' => 'ruc.import', 'name' => 'Importar padrón RUC'],
                ['slug' => 'ruc.import-history', 'name' => 'Ver importaciones RUC'],
                ['slug' => 'ruc.delete-import-file', 'name' => 'Eliminar archivos de importación RUC'],
                ['slug' => 'ruc.cancel-import', 'name' => 'Cancelar importaciones RUC'],
                ['slug' => 'ruc.view-errors', 'name' => 'Ver errores de importación RUC'],
                ['slug' => 'api-tokens.view-own', 'name' => 'Ver tokens propios'],
                ['slug' => 'api-tokens.create-own', 'name' => 'Crear tokens propios'],
                ['slug' => 'api-tokens.revoke-own', 'name' => 'Revocar tokens propios'],
                ['slug' => 'api-tokens.view-any', 'name' => 'Ver todos los tokens'],
                ['slug' => 'api-tokens.create-for-users', 'name' => 'Crear tokens para usuarios'],
                ['slug' => 'api-tokens.revoke-any', 'name' => 'Revocar cualquier token'],
                ['slug' => 'api-token-requests.view', 'name' => 'Ver solicitudes de tokens Telegram'],
                ['slug' => 'api-token-requests.approve', 'name' => 'Aprobar solicitudes de tokens Telegram'],
                ['slug' => 'api-token-requests.reject', 'name' => 'Rechazar solicitudes de tokens Telegram'],
                ['slug' => 'api-token-requests.cancel', 'name' => 'Cancelar solicitudes de tokens Telegram'],
                ['slug' => 'api-token-requests.revoke', 'name' => 'Revocar tokens originados en Telegram'],
                ['slug' => 'api-token-requests.retry-notification', 'name' => 'Reintentar notificaciones n8n'],
                ['slug' => 'api-token-requests.configure', 'name' => 'Configurar integración n8n y Telegram'],
                ['slug' => 'integrations.n8n.manage', 'name' => 'Gestionar integración n8n empresarial'],
                ['slug' => 'integrations.view', 'name' => 'Ver integraciones'],
            ])
                ->map(fn (array $item): array => [
                    'slug' => trim(strtolower($item['slug'])),
                    'name' => trim($item['name']),
                ])
                ->filter(fn (array $item): bool => $item['slug'] !== '' && $item['name'] !== '')
                ->unique('slug')
                ->values();

            $permissions->each(fn (array $item) => Permission::query()->updateOrCreate(
                ['slug' => $item['slug']],
                ['name' => $item['name'], 'description' => null]
            ));

            $this->syncRolePermissions('super-admin', Permission::query()->pluck('id'));
            $this->syncRolePermissions('editor', $this->permissionIdsForSlugs([
                'dashboard.view',
                'agencies.view',
                'agencies.create',
                'agencies.update',
                'agencies.manage_status',
            ]));
            $this->syncRolePermissions('viewer', $this->permissionIdsForSlugs(['agencies.view']));
        });
    }

    /**
     * @param  iterable<int, int|string|null>  $permissionIds
     */
    private function syncRolePermissions(string $roleSlug, iterable $permissionIds): void
    {
        $role = Role::query()->where('slug', trim(strtolower($roleSlug)))->first();

        if ($role === null) {
            return;
        }

        $role->permissions()->sync($this->normalizePermissionIds($permissionIds));
    }

    /**
     * @param  array<int, string|null>  $slugs
     * @return Collection<int, int>
     */
    private function permissionIdsForSlugs(array $slugs): Collection
    {
        $normalizedSlugs = collect($slugs)
            ->map(fn (?string $slug): string => trim(strtolower((string) $slug)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Permission::query()
            ->whereIn('slug', $normalizedSlugs)
            ->pluck('id');
    }

    /**
     * @param  iterable<int, int|string|null>  $permissionIds
     * @return array<int, int>
     */
    private function normalizePermissionIds(iterable $permissionIds): array
    {
        return collect($permissionIds)
            ->filter(fn (int|string|null $id): bool => $id !== null && trim((string) $id) !== '')
            ->map(fn (int|string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
