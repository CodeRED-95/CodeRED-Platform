<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruc_records', function (Blueprint $table): void {
            $table->id();
            $table->string('ruc', 11)->unique();
            $table->text('razon_social');
            $table->string('estado', 60)->nullable()->index();
            $table->string('condicion', 60)->nullable()->index();
            $table->string('ubigeo', 12)->nullable()->index();
            $table->string('tipo_via', 30)->nullable();
            $table->text('nombre_via')->nullable();
            $table->string('codigo_zona', 30)->nullable();
            $table->string('tipo_zona', 60)->nullable();
            $table->string('numero', 30)->nullable();
            $table->string('interior', 30)->nullable();
            $table->string('lote', 30)->nullable();
            $table->string('departamento_direccion', 30)->nullable();
            $table->string('manzana', 30)->nullable();
            $table->string('kilometro', 30)->nullable();
            $table->string('departamento', 120)->nullable()->index();
            $table->string('provincia', 120)->nullable()->index();
            $table->string('distrito', 120)->nullable()->index();
            $table->text('direccion')->nullable();
            $table->timestamps();
        });
        DB::statement('CREATE INDEX ruc_records_razon_social_trgm_index ON ruc_records USING gin (razon_social gin_trgm_ops)');

        Schema::create('ruc_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('disk', 50);
            $table->string('path', 500);
            $table->unsignedBigInteger('file_size');
            $table->string('file_hash', 64)->index();
            $table->string('status', 30)->index();
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('inserted_rows')->default(0);
            $table->unsignedBigInteger('updated_rows')->default(0);
            $table->unsignedBigInteger('ignored_rows')->default(0);
            $table->unsignedBigInteger('invalid_rows')->default(0);
            $table->unsignedBigInteger('failed_rows')->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->unsignedInteger('current_chunk')->default(0);
            $table->unsignedInteger('total_chunks')->default(0);
            $table->string('encoding', 30);
            $table->string('delimiter', 5);
            $table->string('errors_path', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('ruc_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ruc_import_id')->constrained('ruc_imports')->cascadeOnDelete();
            $table->unsignedBigInteger('line_number');
            $table->string('reason', 255);
            $table->text('line_preview')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruc_import_errors');
        Schema::dropIfExists('ruc_imports');
        Schema::dropIfExists('ruc_records');
    }
};
