<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SOLO agregar nuevas tablas y campos, NO modificar existentes

        // Tabla: ruc_import_events (Event Sourcing)
        if (!Schema::hasTable('ruc_import_events')) {
            Schema::create('ruc_import_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ruc_import_id')->constrained('ruc_imports')->cascadeOnDelete();
                $table->string('event_type', 50);
                $table->jsonb('data')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });

            // Crear índices solo si la tabla acaba de ser creada
            if (!DB::table('information_schema.statistics')
                ->where('table_name', 'ruc_import_events')
                ->where('index_name', 'ruc_import_events_ruc_import_id_created_at_index')
                ->exists()) {
                DB::statement('CREATE INDEX ruc_import_events_ruc_import_id_created_at_index ON ruc_import_events(ruc_import_id, created_at)');
            }

            if (!DB::table('information_schema.statistics')
                ->where('table_name', 'ruc_import_events')
                ->where('index_name', 'ruc_import_events_event_type_index')
                ->exists()) {
                DB::statement('CREATE INDEX ruc_import_events_event_type_index ON ruc_import_events(event_type)');
            }
        }

        // Tabla: ruc_import_duplicates (Rastrear duplicados)
        if (!Schema::hasTable('ruc_import_duplicates')) {
            Schema::create('ruc_import_duplicates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ruc_import_id')->constrained('ruc_imports')->cascadeOnDelete();
                $table->string('ruc', 11);
                $table->unsignedBigInteger('first_line');
                $table->unsignedBigInteger('duplicate_line');
                $table->string('action', 30)->default('skipped');
                $table->timestamp('created_at')->useCurrent();
            });

            // Crear índices solo si la tabla acaba de ser creada
            if (!DB::table('information_schema.statistics')
                ->where('table_name', 'ruc_import_duplicates')
                ->where('index_name', 'ruc_import_duplicates_ruc_import_id_index')
                ->exists()) {
                DB::statement('CREATE INDEX ruc_import_duplicates_ruc_import_id_index ON ruc_import_duplicates(ruc_import_id)');
            }

            if (!DB::table('information_schema.statistics')
                ->where('table_name', 'ruc_import_duplicates')
                ->where('index_name', 'ruc_import_duplicates_ruc_index')
                ->exists()) {
                DB::statement('CREATE INDEX ruc_import_duplicates_ruc_index ON ruc_import_duplicates(ruc)');
            }

            if (!DB::table('information_schema.statistics')
                ->where('table_name', 'ruc_import_duplicates')
                ->where('index_name', 'ruc_import_duplicates_ruc_import_id_ruc_duplicate_line_unique')
                ->exists()) {
                DB::statement('CREATE UNIQUE INDEX ruc_import_duplicates_ruc_import_id_ruc_duplicate_line_unique ON ruc_import_duplicates(ruc_import_id, ruc, duplicate_line)');
            }
        }

        // Agregar nuevos campos a ruc_imports (solo si no existen)
        Schema::table('ruc_imports', function (Blueprint $table): void {
            if (!Schema::hasColumn('ruc_imports', 'valid_lines')) {
                $table->unsignedBigInteger('valid_lines')->default(0);
            }
            if (!Schema::hasColumn('ruc_imports', 'skipped_lines')) {
                $table->unsignedBigInteger('skipped_lines')->default(0);
            }
            if (!Schema::hasColumn('ruc_imports', 'warning_lines')) {
                $table->unsignedBigInteger('warning_lines')->default(0);
            }
            if (!Schema::hasColumn('ruc_imports', 'updated_records')) {
                $table->unsignedBigInteger('updated_records')->default(0);
            }
            if (!Schema::hasColumn('ruc_imports', 'duplicate_records')) {
                $table->unsignedBigInteger('duplicate_records')->default(0)->comment('Duplicados dentro del archivo');
            }
            if (!Schema::hasColumn('ruc_imports', 'skipped_records')) {
                $table->unsignedBigInteger('skipped_records')->default(0)->comment('Registros omitidos por configuración');
            }
            if (!Schema::hasColumn('ruc_imports', 'paused_at')) {
                $table->timestamp('paused_at')->nullable();
            }
            if (!Schema::hasColumn('ruc_imports', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (!Schema::hasColumn('ruc_imports', 'checkpoint_line')) {
                $table->unsignedBigInteger('checkpoint_line')->default(0);
            }
            if (!Schema::hasColumn('ruc_imports', 'checkpoint_byte_offset')) {
                $table->unsignedBigInteger('checkpoint_byte_offset')->default(0);
            }
            if (!Schema::hasColumn('ruc_imports', 'checkpoint_timestamp')) {
                $table->timestamp('checkpoint_timestamp')->nullable();
            }
            if (!Schema::hasColumn('ruc_imports', 'merge_strategy')) {
                $table->string('merge_strategy', 30)->default('insert')->comment('insert|insert_update|replace');
            }
            if (!Schema::hasColumn('ruc_imports', 'skip_duplicates')) {
                $table->boolean('skip_duplicates')->default(true)->comment('¿Saltar duplicados en archivo?');
            }
            if (!Schema::hasColumn('ruc_imports', 'skip_unknown_ubigeo')) {
                $table->boolean('skip_unknown_ubigeo')->default(false)->comment('¿Saltar UBIGEO desconocidos?');
            }
            if (!Schema::hasColumn('ruc_imports', 'max_errors_allowed')) {
                $table->integer('max_errors_allowed')->nullable()->comment('Máx errores antes de abortar (null = sin límite)');
            }
            if (!Schema::hasColumn('ruc_imports', 'rollback_requested_at')) {
                $table->timestamp('rollback_requested_at')->nullable();
            }
            if (!Schema::hasColumn('ruc_imports', 'rollback_started_at')) {
                $table->timestamp('rollback_started_at')->nullable();
            }
            if (!Schema::hasColumn('ruc_imports', 'rollback_completed_at')) {
                $table->timestamp('rollback_completed_at')->nullable();
            }
            if (!Schema::hasColumn('ruc_imports', 'rollback_reason')) {
                $table->text('rollback_reason')->nullable();
            }
            if (!Schema::hasColumn('ruc_imports', 'last_error')) {
                $table->text('last_error')->nullable()->comment('Últimos 500 chars del error');
            }
            if (!Schema::hasColumn('ruc_imports', 'last_warning')) {
                $table->text('last_warning')->nullable()->comment('Última advertencia');
            }
            if (!Schema::hasColumn('ruc_imports', 'status_message')) {
                $table->text('status_message')->nullable()->comment('Mensaje de estado actual');
            }
            if (!Schema::hasColumn('ruc_imports', 'memory_peak_mb')) {
                $table->integer('memory_peak_mb')->nullable()->comment('Pico de memoria usado');
            }
            if (!Schema::hasColumn('ruc_imports', 'duration_seconds')) {
                $table->integer('duration_seconds')->nullable()->comment('Duración total en segundos');
            }
            if (!Schema::hasColumn('ruc_imports', 'lines_per_second')) {
                $table->decimal('lines_per_second', 10, 2)->nullable()->comment('Velocidad promedio');
            }
            if (!Schema::hasColumn('ruc_imports', 'estimated_time_left')) {
                $table->integer('estimated_time_left')->nullable()->comment('ETA en segundos');
            }
        });

        // Agregar campos a ruc_import_errors
        Schema::table('ruc_import_errors', function (Blueprint $table): void {
            if (!Schema::hasColumn('ruc_import_errors', 'error_code')) {
                $table->string('error_code', 50)->nullable()->after('reason')->comment('INVALID_RUC, EMPTY_RAZON_SOCIAL, etc');
            }
            if (!Schema::hasColumn('ruc_import_errors', 'error_category')) {
                $table->string('error_category', 30)->nullable()->after('error_code')->comment('validation|duplicate|system');
            }
            if (!Schema::hasColumn('ruc_import_errors', 'resolved')) {
                $table->boolean('resolved')->default(false)->after('error_category')->comment('¿Resuelto manualmente?');
            }
            if (!Schema::hasColumn('ruc_import_errors', 'resolved_by')) {
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('ruc_import_errors', 'resolution_notes')) {
                $table->text('resolution_notes')->nullable();
            }
        });

        // No crear índices aquí - solo agregar columnas a tabla existente
    }

    public function down(): void
    {
        // ROLLBACK: solo dropear tablas nuevas, no eliminar columnas
        Schema::dropIfExists('ruc_import_duplicates');
        Schema::dropIfExists('ruc_import_events');

        // Opcionalmente eliminar columnas nuevas de ruc_imports y ruc_import_errors
        // (pero es seguro dejarlas)
    }
};
