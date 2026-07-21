<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reniec_imports', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('original_filename');
            $t->string('stored_filename');
            $t->string('disk');
            $t->text('source_path');
            $t->text('working_path')->nullable();
            $t->text('archive_path')->nullable();
            $t->text('error_file_path')->nullable();
            $t->unsignedBigInteger('file_size');
            $t->char('file_hash', 64);
            $t->string('status', 40);
            $t->string('strategy', 30);
            foreach (['total_rows', 'processed_rows', 'valid_rows', 'invalid_rows', 'inserted_rows', 'updated_rows', 'ignored_rows', 'duplicate_rows', 'failed_rows', 'current_byte_offset', 'current_line_number', 'last_completed_chunk', 'total_chunks'] as $c) {
                $t->unsignedBigInteger($c)->default(0);
            }$t->decimal('rows_per_second', 12, 2)->default(0);
            $t->unsignedBigInteger('estimated_seconds_remaining')->nullable();
            foreach (['started_at', 'finished_at', 'failed_at', 'cancel_requested_at', 'cancelled_at', 'paused_at', 'resumed_at', 'last_heartbeat_at'] as $c) {
                $t->timestamp($c)->nullable();
            }$t->text('error_message')->nullable();
            $t->jsonb('metadata')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
        Schema::create('reniec_import_staging', function (Blueprint $t): void {
            $t->foreignId('import_id')->constrained('reniec_imports')->cascadeOnDelete();
            $t->unsignedBigInteger('row_number');
            $t->string('dni', 8);
            $t->string('nombres', 120);
            $t->string('apellido_paterno', 120);
            $t->string('apellido_materno', 120);
            $t->date('fecha_nacimiento')->nullable();
            $t->string('genero', 20)->nullable();
            $t->string('ubigeo', 6)->nullable();
            $t->unique(['import_id', 'row_number']);
            $t->index(['import_id', 'dni']);
        });
        if (DB::getDriverName() === 'pgsql' && config('reniec.staging_unlogged')) {
            DB::statement('ALTER TABLE reniec_import_staging SET UNLOGGED');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reniec_import_staging');
        Schema::dropIfExists('reniec_imports');
    }
};
