<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simplifica ruc_backups para el nuevo sistema de backup/restore (ver
 * docs-ruc/BACKUP_SYSTEM.md). Los campos mínimos ahora son: id, name,
 * storage_path, file_size_bytes, checksum_sha256, total_records, status,
 * created_by, created_at, updated_at.
 *
 * No destructiva: solo relaja columnas NOT NULL sin default (backup_type,
 * storage_type) para que el nuevo código, más simple, no necesite
 * poblarlas. NO se borra ninguna columna ni fila existente — los backups ya
 * creados siguen siendo válidos y legibles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruc_backups', function (Blueprint $table): void {
            if (Schema::hasColumn('ruc_backups', 'backup_type')) {
                $table->string('backup_type', 50)->nullable()->default(null)->change();
            }
            if (Schema::hasColumn('ruc_backups', 'storage_type')) {
                $table->string('storage_type', 50)->nullable()->default(null)->change();
            }
        });
    }

    public function down(): void
    {
        // No revertimos a NOT NULL: podría haber filas nuevas con estos
        // campos en null, y forzar el rollback las dejaría inválidas.
    }
};
