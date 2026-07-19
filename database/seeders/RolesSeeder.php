<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $super = Role::query()->updateOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador', 'description' => 'Acceso total', 'is_system' => true]);
            $editor = Role::query()->updateOrCreate(['slug' => 'editor'], ['name' => 'Editor', 'description' => 'Gestión operativa de agencias', 'is_system' => true]);
            Role::query()->updateOrCreate(['slug' => 'viewer'], ['name' => 'Consulta', 'description' => 'Consulta de agencias y mapa', 'is_system' => true]);

            $legacyAdmin = Role::query()->where('slug', 'admin')->first();
            if ($legacyAdmin !== null) {
                $operationalUserIds = $legacyAdmin->users()->whereDoesntHave('roles', fn ($query) => $query->whereKey($super->id))->pluck('users.id');
                foreach ($operationalUserIds as $userId) {
                    DB::table('role_user')->insertOrIgnore(['role_id' => $editor->id, 'user_id' => $userId]);
                }
                $legacyAdmin->delete();
            }
        });
    }
}
