<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira el sistema de IMPORTACIÓN RUC. La administración del padrón pasa a
 * hacerse exclusivamente con backup/restore (ver app/Modules/Ruc/BACKUP_SYSTEM.md).
 *
 * NO TOCA los datos del padrón:
 *   - ruc_records            se conserva íntegra (ni TRUNCATE ni DELETE)
 *   - ruc_backups            se conserva
 *   - ruc_backup_operations  se conserva
 *   - ruc_backup_uploads*    se conservan
 *
 * Orden de borrado: primero las dependencias hacia ruc_imports (la FK de
 * ruc_records y las tablas hijas), después ruc_imports. Invertirlo haría
 * fallar el DROP por violación de clave foránea.
 *
 * Sobre ruc_records.ruc_import_id: solo servía para acotar el rollback de una
 * importación. Sin importaciones queda huérfana, y su FK impide soltar
 * ruc_imports. En PostgreSQL, DROP COLUMN es una operación de catálogo
 * (metadata-only): no reescribe la tabla ni toca los datos del padrón, así que
 * es segura incluso con 18M+ filas.
 */
return new class extends Migration
{
    /** Tablas exclusivas de importación, en orden de dependencia (hijas primero). */
    private const IMPORT_TABLES = [
        'ruc_import_duplicates',
        'ruc_import_events',
        'ruc_import_errors',
        'ruc_staging',
        'ruc_imports',
    ];

    public function up(): void
    {
        // 1. Quitar la FK de ruc_records hacia ruc_imports antes que nada.
        if (Schema::hasTable('ruc_records') && Schema::hasColumn('ruc_records', 'ruc_import_id')) {
            Schema::table('ruc_records', function (Blueprint $table): void {
                // El nombre por defecto de Laravel es <tabla>_<columna>_foreign.
                // dropForeign() con array deja que el driver lo resuelva.
                try {
                    $table->dropForeign(['ruc_import_id']);
                } catch (\Throwable) {
                    // La FK ya no existía (base parcialmente migrada): continuar.
                }
            });

            Schema::table('ruc_records', function (Blueprint $table): void {
                $table->dropColumn('ruc_import_id');
            });
        }

        // 2. Soltar las tablas de importación, hijas primero.
        foreach (self::IMPORT_TABLES as $table) {
            Schema::dropIfExists($table);
        }

        // 3. ruc_statistics.total_imports pierde sentido sin importaciones.
        //    La tabla tiene una sola fila; es un cambio trivial y reversible.
        if (Schema::hasTable('ruc_statistics') && Schema::hasColumn('ruc_statistics', 'total_imports')) {
            Schema::table('ruc_statistics', function (Blueprint $table): void {
                $table->dropColumn('total_imports');
            });
        }
    }

    /**
     * Reversión de la ESTRUCTURA, no de los datos: las filas de importación se
     * pierden al hacer el DROP y no pueden recuperarse desde aquí. Se recrean
     * las tablas con la forma mínima que tenían para que un rollback deje la
     * base en un estado consistente y migrable.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ruc_imports')) {
            Schema::create('ruc_imports', function (Blueprint $table): void {
                $table->id();
                $table->string('original_filename')->nullable();
                $table->string('stored_path')->nullable();
                $table->string('status')->default('queued')->index();
                $table->unsignedBigInteger('total_rows')->default(0);
                $table->unsignedBigInteger('processed_rows')->default(0);
                $table->unsignedBigInteger('inserted_rows')->default(0);
                $table->unsignedBigInteger('ignored_rows')->default(0);
                $table->unsignedBigInteger('invalid_rows')->default(0);
                $table->text('last_message')->nullable();
                $table->text('error_message')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('cancel_requested_at')->nullable();
                $table->timestamp('last_heartbeat_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ruc_import_errors')) {
            Schema::create('ruc_import_errors', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ruc_import_id')->constrained('ruc_imports')->cascadeOnDelete();
                $table->unsignedBigInteger('line_number')->nullable();
                $table->text('raw_line')->nullable();
                $table->text('reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ruc_import_events')) {
            Schema::create('ruc_import_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ruc_import_id')->constrained('ruc_imports')->cascadeOnDelete();
                $table->string('event_type')->index();
                $table->json('data')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip_address')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ruc_import_duplicates')) {
            Schema::create('ruc_import_duplicates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ruc_import_id')->constrained('ruc_imports')->cascadeOnDelete();
                $table->string('ruc', 11)->index();
                $table->unsignedBigInteger('line_number')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('ruc_records') && ! Schema::hasColumn('ruc_records', 'ruc_import_id')) {
            Schema::table('ruc_records', function (Blueprint $table): void {
                $table->foreignId('ruc_import_id')->nullable()->after('id')->constrained('ruc_imports')->nullOnDelete();
                $table->index('ruc_import_id');
            });
        }

        if (Schema::hasTable('ruc_statistics') && ! Schema::hasColumn('ruc_statistics', 'total_imports')) {
            Schema::table('ruc_statistics', function (Blueprint $table): void {
                $table->unsignedBigInteger('total_imports')->default(0);
            });
            DB::table('ruc_statistics')->update(['total_imports' => 0]);
        }
    }
};
