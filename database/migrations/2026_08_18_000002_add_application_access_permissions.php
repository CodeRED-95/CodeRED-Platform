<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos de acceso por aplicación.
 *
 * Hasta ahora "poder entrar en Mobile" era implícito: cualquiera con
 * credenciales válidas obtenía un token con la ability `mobile`. A partir de
 * aquí es un permiso administrable más, igual que ver agencias o consultar RUC.
 *
 * Los tres permisos se conceden a TODOS los roles existentes. Es deliberado: si
 * naciesen sin conceder, el despliegue dejaría fuera a quienes hoy usan Mobile
 * en producción. La migración preserva el acceso efectivo actual y deja que la
 * administración lo recorte después desde la ficha del usuario.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'platform.access' => 'Acceder a CodeRED Platform',
        'mobile.access' => 'Acceder a CodeRED Mobile',
        'desktop.access' => 'Acceder a CodeRED Desktop',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            foreach (self::PERMISSIONS as $slug => $name) {
                $exists = DB::table('permissions')->where('slug', $slug)->exists();

                if (! $exists) {
                    DB::table('permissions')->insert([
                        'slug' => $slug,
                        'name' => $name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('slug', array_keys(self::PERMISSIONS))
                ->pluck('id');

            $roleIds = DB::table('roles')->pluck('id');

            $rows = [];

            foreach ($roleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    $rows[] = [
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ];
                }
            }

            if ($rows !== []) {
                // insertOrIgnore para que reejecutar la migración sea inocuo.
                DB::table('permission_role')->insertOrIgnore($rows);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', array_keys(self::PERMISSIONS))
                ->pluck('id');

            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        });
    }
};
