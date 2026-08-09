<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira los permisos del sistema de importación RUC, eliminado en la v3.0.0.
 *
 * Salieron de PermissionsSeeder en el mismo cambio, pero las filas ya creadas
 * seguían en base de datos concediendo acceso a rutas y pantallas que ya no
 * existen: permisos inertes que solo ensucian la matriz de autorización.
 *
 * Alcance MUY acotado: únicamente estos cinco slugs. No toca ningún otro
 * permiso, rol, usuario ni dato de negocio. El pivote `permission_role` tiene
 * ON DELETE CASCADE, pero se limpia explícitamente para no depender de ello.
 */
return new class extends Migration
{
    private const REMOVED_PERMISSIONS = [
        'ruc.import' => 'Importar padrón RUC',
        'ruc.import-history' => 'Ver importaciones RUC',
        'ruc.delete-import-file' => 'Eliminar archivos de importación RUC',
        'ruc.cancel-import' => 'Cancelar importaciones RUC',
        'ruc.view-errors' => 'Ver errores de importación RUC',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')
            ->whereIn('slug', array_keys(self::REMOVED_PERMISSIONS))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        if (Schema::hasTable('permission_role')) {
            DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        }

        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    /**
     * Recrea los permisos, pero NO los reasigna a ningún rol: qué rol los tenía
     * es información que se pierde al borrar el pivote y adivinarla sería peor
     * que dejar la reasignación en manos del operador.
     */
    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (self::REMOVED_PERMISSIONS as $slug => $name) {
            $exists = DB::table('permissions')->where('slug', $slug)->exists();

            if ($exists) {
                continue;
            }

            DB::table('permissions')->insert([
                'slug' => $slug,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
