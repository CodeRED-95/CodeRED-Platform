<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkpoints por lote para la restauración troceada (.rucbackup).
 *
 * Sin estas columnas una restauración interrumpida solo puede reiniciarse
 * desde cero. Con ellas, `ruc:restore --resume` sabe exactamente qué lote fue
 * el último confirmado y continúa desde el siguiente.
 *
 * No toca ruc_records ni ninguna tabla de datos: solo añade columnas
 * nullable a la tabla de seguimiento de operaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ruc_backup_operations')) {
            return;
        }

        Schema::table('ruc_backup_operations', function (Blueprint $table): void {
            if (! Schema::hasColumn('ruc_backup_operations', 'total_batches')) {
                $table->unsignedInteger('total_batches')->nullable()->after('progress');
            }
            if (! Schema::hasColumn('ruc_backup_operations', 'current_batch')) {
                $table->unsignedInteger('current_batch')->nullable()->after('total_batches');
            }
            if (! Schema::hasColumn('ruc_backup_operations', 'completed_batches')) {
                $table->unsignedInteger('completed_batches')->default(0)->after('current_batch');
            }
            if (! Schema::hasColumn('ruc_backup_operations', 'records_processed')) {
                $table->unsignedBigInteger('records_processed')->default(0)->after('completed_batches');
            }
            if (! Schema::hasColumn('ruc_backup_operations', 'bytes_processed')) {
                $table->unsignedBigInteger('bytes_processed')->default(0)->after('records_processed');
            }
            if (! Schema::hasColumn('ruc_backup_operations', 'staging_table')) {
                // Nombre real de la tabla de staging (ruc_records_next). Se
                // persiste en vez de asumirse para que un resume pueda
                // comprobar que la tabla que encuentra es la de ESTA
                // operación y no un resto de otra anterior.
                $table->string('staging_table')->nullable()->after('bytes_processed');
            }
            if (! Schema::hasColumn('ruc_backup_operations', 'cancel_requested_at')) {
                $table->timestamp('cancel_requested_at')->nullable()->after('staging_table');
            }
            if (! Schema::hasColumn('ruc_backup_operations', 'checkpoint')) {
                // Instantánea del último lote confirmado (JSON): número de
                // lote, sha256 del chunk y conteo acumulado. Permite validar
                // la reanudación en vez de confiar solo en un contador.
                $table->json('checkpoint')->nullable()->after('cancel_requested_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ruc_backup_operations')) {
            return;
        }

        Schema::table('ruc_backup_operations', function (Blueprint $table): void {
            foreach ([
                'total_batches', 'current_batch', 'completed_batches',
                'records_processed', 'bytes_processed', 'staging_table',
                'cancel_requested_at', 'checkpoint',
            ] as $column) {
                if (Schema::hasColumn('ruc_backup_operations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
