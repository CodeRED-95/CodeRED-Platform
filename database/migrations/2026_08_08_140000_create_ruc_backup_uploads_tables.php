<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiones de subida multipart de backups RUC generados por RUC Tools
 * (manifest.json + *.partNNNN, ver packages/ruc-tools). Cada parte se sube
 * en un request HTTP separado (evita 413 de Cloudflare); esta tabla permite
 * saber qué partes ya llegaron para poder reanudar sin volver a subirlas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ruc_backup_uploads')) {
            Schema::create('ruc_backup_uploads', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 20)->default('pending'); // pending, uploading, assembling, validating, completed, failed, cancelled
                $table->string('original_filename');
                $table->json('manifest_json');
                $table->unsignedBigInteger('total_size_bytes');
                $table->unsignedBigInteger('part_size_bytes');
                $table->unsignedInteger('total_parts');
                $table->unsignedInteger('received_parts')->default(0);
                $table->unsignedBigInteger('received_bytes')->default(0);
                $table->string('temporary_directory');
                $table->foreignId('ruc_backup_id')->nullable()->constrained('ruc_backups')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('expires_at');
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('expires_at');
            });
        }

        if (! Schema::hasTable('ruc_backup_upload_parts')) {
            Schema::create('ruc_backup_upload_parts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('upload_id')->constrained('ruc_backup_uploads')->cascadeOnDelete();
                $table->unsignedInteger('part_index');
                $table->string('filename');
                $table->unsignedBigInteger('size_bytes');
                $table->string('checksum_sha256', 64);
                $table->string('storage_path')->nullable(); // se completa al recibir la parte
                $table->string('status', 20)->default('pending'); // pending, uploaded, verified, failed
                $table->timestamps();

                $table->unique(['upload_id', 'part_index']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ruc_backup_upload_parts');
        Schema::dropIfExists('ruc_backup_uploads');
    }
};
