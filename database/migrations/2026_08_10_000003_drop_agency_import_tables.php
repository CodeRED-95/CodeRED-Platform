<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retira el sistema de IMPORTACIÓN MANUAL de agencias. La administración del
 * padrón pasa a hacerse con backup/restore (agency_backups +
 * agency_backup_restores).
 *
 * NO TOCA los datos de agencias ni la sincronización Shalom:
 *   - agencies                se conserva íntegra
 *   - agency_name_histories   se conserva
 *   - agency_sync_changes     se conserva (feed incremental de la API)
 *   - agency_sync_states      se conserva
 *   - agency_change_logs      se conserva (auditoría)
 *   - agency_import_runs      SE CONSERVA: es el registro de ejecuciones de la
 *   - agency_import_items     sincronización Shalom, que sigue en uso.
 *
 * Solo se sueltan las dos tablas que usaba en exclusiva el importador manual,
 * hijas primero para no violar la clave foránea.
 */
return new class extends Migration
{
    /** Tablas exclusivas del importador manual, en orden de dependencia. */
    private const IMPORT_TABLES = [
        'agency_import_failures',
        'agency_imports',
    ];

    public function up(): void
    {
        foreach (self::IMPORT_TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Reversión de la ESTRUCTURA, no de los datos: las filas se pierden con el
     * DROP y no pueden recuperarse desde aquí. Se recrean con la forma que
     * tenían para que un rollback deje la base en un estado consistente.
     */
    public function down(): void
    {
        if (! Schema::hasTable('agency_imports')) {
            Schema::create('agency_imports', function (Blueprint $table): void {
                $table->id();
                $table->string('original_filename')->nullable();
                $table->string('stored_path')->nullable();
                $table->string('status', 40)->default('pending')->index();
                $table->string('strategy', 40)->default('ignore_existing');
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('imported_rows')->default(0);
                $table->unsignedInteger('updated_rows')->default(0);
                $table->unsignedInteger('skipped_rows')->default(0);
                $table->unsignedInteger('failed_rows')->default(0);
                $table->text('error_message')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agency_import_failures')) {
            Schema::create('agency_import_failures', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agency_import_id')->constrained('agency_imports')->cascadeOnDelete();
                $table->unsignedInteger('row_number')->nullable();
                $table->json('payload')->nullable();
                $table->text('reason')->nullable();
                $table->timestamps();
            });
        }
    }
};
