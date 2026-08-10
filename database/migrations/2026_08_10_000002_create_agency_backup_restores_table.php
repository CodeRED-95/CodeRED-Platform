<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de restauraciones de copias de agencias.
 *
 * La restauración se ejecuta en cola (RestoreAgencyBackupJob) y esta tabla es
 * lo que la interfaz consulta para mostrar estado y progreso: así ninguna
 * petición HTTP queda esperando al proceso y no hay riesgo de timeout de
 * Cloudflare por muy grande que sea la copia.
 *
 * Es idempotente: solo crea la tabla si no existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agency_backup_restores')) {
            return;
        }

        Schema::create('agency_backup_restores', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            // Una restauración puede partir de una copia ya registrada o de un
            // archivo subido a mano; por eso la relación es opcional.
            $table->foreignId('agency_backup_id')->nullable()->constrained('agency_backups')->nullOnDelete();
            $table->string('filename');
            $table->string('disk', 50)->default('local');
            $table->string('path', 500);
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            // merge: solo crea y actualiza. replace: además envía a la papelera
            // las agencias que no estén en la copia (nunca borrado definitivo).
            $table->string('mode', 20)->default('merge');

            $table->string('status', 20)->default('pending')->index();
            $table->string('stage', 120)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);

            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('created_records')->default(0);
            $table->unsignedInteger('updated_records')->default(0);
            $table->unsignedInteger('trashed_records')->default(0);
            $table->unsignedInteger('name_histories_restored')->default(0);

            // Copia automática tomada justo antes de escribir nada, para poder
            // deshacer una restauración equivocada.
            $table->foreignId('safety_backup_id')->nullable()->constrained('agency_backups')->nullOnDelete();

            $table->text('error_message')->nullable();
            $table->json('summary')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_backup_restores');
    }
};
