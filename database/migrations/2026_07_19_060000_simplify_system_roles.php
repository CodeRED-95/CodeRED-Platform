<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || DB::table('roles')->doesntExist()) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            DB::table('roles')->updateOrInsert(['slug' => 'super-admin'], ['name' => 'Super Administrador', 'description' => 'Acceso total', 'is_system' => true, 'updated_at' => $now, 'created_at' => $now]);
            DB::table('roles')->updateOrInsert(['slug' => 'editor'], ['name' => 'Editor', 'description' => 'Gestión operativa de agencias', 'is_system' => true, 'updated_at' => $now, 'created_at' => $now]);
            DB::table('roles')->updateOrInsert(['slug' => 'viewer'], ['name' => 'Consulta', 'description' => 'Consulta de agencias y mapa', 'is_system' => true, 'updated_at' => $now, 'created_at' => $now]);

            $adminId = DB::table('roles')->where('slug', 'admin')->value('id');
            $editorId = DB::table('roles')->where('slug', 'editor')->value('id');
            $superId = DB::table('roles')->where('slug', 'super-admin')->value('id');
            if ($adminId !== null && $editorId !== null && $superId !== null) {
                $operationalUsers = DB::table('role_user')->where('role_id', $adminId)
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('role_user as super_roles')->whereColumn('super_roles.user_id', 'role_user.user_id')->where('super_roles.role_id', $superId))
                    ->pluck('user_id');
                foreach ($operationalUsers as $userId) {
                    DB::table('role_user')->insertOrIgnore(['role_id' => $editorId, 'user_id' => $userId]);
                }
                DB::table('role_user')->where('role_id', $adminId)->delete();
                DB::table('roles')->where('id', $adminId)->delete();
            }

            $matrix = [
                'super-admin' => DB::table('permissions')->pluck('id')->all(),
                'editor' => DB::table('permissions')->whereIn('slug', ['dashboard.view', 'agencies.view', 'agencies.create', 'agencies.update', 'agencies.manage_status'])->pluck('id')->all(),
                'viewer' => DB::table('permissions')->whereIn('slug', ['agencies.view'])->pluck('id')->all(),
            ];
            foreach ($matrix as $slug => $permissionIds) {
                $roleId = DB::table('roles')->where('slug', $slug)->value('id');
                if ($roleId === null) {
                    continue;
                }
                DB::table('permission_role')->where('role_id', $roleId)->delete();
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }
        });
    }

    public function down(): void
    {
        // La equivalencia admin → editor no se revierte para evitar degradar o duplicar asignaciones de usuarios.
    }
};
