<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos del panel de bloqueo de la extension.
 *
 * Solo se conceden a super-admin: el bloqueo horario afecta a todas las
 * instalaciones conectadas por token, asi que no se hereda a editor ni viewer.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'settings.extension-blocking.view' => 'Ver bloqueo horario de la extension',
        'settings.extension-blocking.manage' => 'Gestionar bloqueo horario de la extension',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            foreach (self::PERMISSIONS as $slug => $name) {
                if (! DB::table('permissions')->where('slug', $slug)->exists()) {
                    DB::table('permissions')->insert([
                        'slug' => $slug,
                        'name' => $name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
            $superAdminId = DB::table('roles')->where('slug', 'super-admin')->value('id');

            if ($superAdminId === null) {
                return;
            }

            $rows = $permissionIds
                ->map(fn ($permissionId): array => ['role_id' => $superAdminId, 'permission_id' => $permissionId])
                ->all();

            if ($rows !== []) {
                DB::table('permission_role')->insertOrIgnore($rows);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        });
    }
};
