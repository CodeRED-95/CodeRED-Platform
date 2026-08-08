<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado persistente de una operación pesada sobre ruc_records (por ahora
 * solo "restore"), ejecutada de forma asíncrona por RestoreRucBackupJob en
 * la cola dedicada "ruc-backups". Nunca se ejecuta dentro del request HTTP
 * (ver app/Modules/Ruc/Http/Controllers/RucBackupController::restore()) —
 * esta tabla es la fuente de verdad que la UI consulta por polling y que
 * update.sh consulta antes de reiniciar contenedores.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ruc_backup_operations')) {
            Schema::create('ruc_backup_operations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('backup_id')->constrained('ruc_backups')->cascadeOnDelete();
                $table->string('operation_type', 20)->default('restore');
                $table->string('status', 20)->default('pending'); // pending, running, completed, failed
                $table->string('stage', 30)->default('queued');
                $table->unsignedTinyInteger('progress')->default(0);
                $table->string('message')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('safety_backup_id')->nullable()->constrained('ruc_backups')->nullOnDelete();
                $table->unsignedBigInteger('records_before')->nullable();
                $table->unsignedBigInteger('records_after')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                // Consultada constantemente: por el endpoint de polling, por
                // el controller (¿hay otro restore activo?) y por update.sh
                // (¿es seguro reiniciar contenedores?).
                $table->index('status');
                $table->index(['backup_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ruc_backup_operations');
    }
};
