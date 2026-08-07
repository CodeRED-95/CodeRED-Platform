<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ruc_backups')) {
            return;
        }
        Schema::create('ruc_backups', function (Blueprint $table): void {
            $table->id();
            $table->string('name'); // nombre: ruc_backup_2026-08-06-143000.sql.gz
            $table->string('backup_type', 50); // full, incremental, manual
            $table->bigInteger('total_records')->nullable(); // cantidad de registros
            $table->bigInteger('file_size_bytes')->nullable(); // tamaño del archivo comprimido
            $table->string('storage_path'); // ruta en S3 o almacenamiento local
            $table->string('storage_type', 50); // s3, local, gcs
            $table->string('compression_type', 50)->default('gzip'); // gzip, bzip2, none
            $table->string('status', 50)->default('pending'); // pending, completed, failed, deleted
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable(); // tiempo de backup
            $table->text('error_message')->nullable();
            $table->string('checksum_sha256')->nullable(); // para validar integridad
            $table->integer('retention_days')->default(30); // cuantos días mantener
            $table->timestamp('expires_at')->nullable(); // cuándo se elimina automáticamente
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Índices
            $table->index('status');
            $table->index('backup_type');
            $table->index('created_at');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruc_backups');
    }
};
