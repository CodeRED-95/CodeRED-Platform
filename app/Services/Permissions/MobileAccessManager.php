<?php

declare(strict_types=1);

namespace App\Services\Permissions;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Concede y retira accesos móviles.
 *
 * Es el único sitio del código que otorga uno de estos permisos, y por eso
 * concentra la decisión de cómo se transporta: mediante un rol dedicado que
 * contiene exactamente ese permiso y nada más. Ver <see cref="MobileAccess"/>
 * para el porqué.
 *
 * Conceder es idempotente: si el usuario ya tiene el acceso —por este rol o
 * porque su rol principal ya incluía el permiso—, no ocurre nada. Retirar sólo
 * quita el rol dedicado: si el permiso le llega además por su rol principal,
 * sigue teniéndolo, y eso es correcto. Retirar un acceso móvil no puede
 * desmontar la configuración de roles de nadie.
 */
final class MobileAccessManager
{
    /**
     * Otorga el acceso. Devuelve si hubo cambio real.
     */
    public function grant(User $user, string $permission): bool
    {
        if (! MobileAccess::isRequestable($permission)) {
            return false;
        }

        $role = $this->ensureRole($permission);

        if ($user->roles()->where('roles.id', $role->getKey())->exists()) {
            return false;
        }

        $user->roles()->attach($role->getKey());
        $user->unsetRelation('roles');

        Log::info('permission_granted', [
            'user_id' => $user->getKey(),
            'permission' => $permission,
            'via_role' => $role->slug,
        ]);

        return true;
    }

    /**
     * Retira el rol de acceso. No toca ningún otro rol del usuario.
     */
    public function revoke(User $user, string $permission): bool
    {
        $slug = MobileAccess::role($permission);

        if ($slug === null) {
            return false;
        }

        $role = Role::query()->where('slug', $slug)->first();

        if ($role === null || ! $user->roles()->where('roles.id', $role->getKey())->exists()) {
            return false;
        }

        $user->roles()->detach($role->getKey());
        $user->unsetRelation('roles');

        Log::info('permission_revoked', [
            'user_id' => $user->getKey(),
            'permission' => $permission,
            'via_role' => $role->slug,
        ]);

        return true;
    }

    /**
     * Estado de todos los accesos móviles de un usuario, para la pantalla de
     * administración y para el propio interesado.
     *
     * Distingue dos cosas que parecen una: tener el acceso, y tenerlo por este
     * mecanismo. Un usuario cuyo rol principal ya incluye `ruc.view` lo tiene
     * concedido, pero retirarle el rol de acceso no se lo quitaría — y la
     * interfaz debe poder decirlo.
     *
     * @return list<array{permission: string, label: string, description: string, granted: bool, revocable: bool}>
     */
    public function statusFor(User $user): array
    {
        $estados = [];

        foreach (MobileAccess::all() as $acceso) {
            $permission = $acceso['permission'];
            $slug = MobileAccess::role($permission);

            $estados[] = [
                'permission' => $permission,
                'label' => $acceso['label'],
                'description' => $acceso['description'],
                'granted' => $user->hasPermission($permission),
                'revocable' => $slug !== null
                    && $user->roles()->where('roles.slug', $slug)->exists(),
            ];
        }

        return $estados;
    }

    /**
     * El rol que transporta el permiso, creándolo si hace falta.
     *
     * Se crea aquí y no sólo en el seeder para que una instalación que aún no
     * haya pasado por él pueda conceder accesos igualmente: el primer uso deja
     * el rol listo.
     */
    private function ensureRole(string $permission): Role
    {
        $slug = MobileAccess::role($permission);

        return DB::transaction(function () use ($slug, $permission): Role {
            $role = Role::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => MobileAccess::roleName($permission)]
            );

            $permissionModel = Permission::query()->firstOrCreate(
                ['slug' => $permission],
                ['name' => MobileAccess::label($permission)]
            );

            if (! $role->permissions()->where('permissions.id', $permissionModel->getKey())->exists()) {
                $role->permissions()->attach($permissionModel->getKey());
            }

            return $role;
        });
    }
}
