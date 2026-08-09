<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de estadísticas persistentes de ruc_records, actualizada después
 * de cada import/restore masivo. Evita COUNT(*) en endpoints frecuentes
 * (p. ej. RucBackupController::index()). Las aplicaciones leen de esta
 * tabla + cache en vez de ejecutar seq scans costosos sobre 18M+ filas.
 * Ver docs-ruc/PERFORMANCE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ruc_statistics')) {
            Schema::create('ruc_statistics', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('total_records')->default(0);
                $table->unsignedBigInteger('total_imports')->default(0);
                $table->timestamp('last_import_at')->nullable();
                $table->timestamp('last_restore_at')->nullable();
                $table->timestamp('last_analyzed_at')->nullable();
                $table->timestamps();

                // Consultada frecuentemente por cache invalidation checks
                $table->index('updated_at');
            });

            // Inicializar con valor actual (único registro en esta tabla)
            DB::table('ruc_statistics')->insert([
                'total_records' => DB::table('ruc_records')->count(),
                // ruc_imports se elimina en 2026_08_09_000001_drop_ruc_import_tables.
                // En un despliegue limpio esa migración corre DESPUÉS de esta, así
                // que la tabla todavía existe aquí; el guard evita depender de ese
                // orden si algún día se consolidan las migraciones.
                'total_imports' => Schema::hasTable('ruc_imports') ? DB::table('ruc_imports')->count() : 0,
                'last_analyzed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ruc_statistics');
    }
};
