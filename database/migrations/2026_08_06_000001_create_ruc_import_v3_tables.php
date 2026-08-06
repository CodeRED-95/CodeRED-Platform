<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expandir tabla ruc_imports con nuevos campos
        Schema::table('ruc_imports', function (Blueprint $table): void {
            // Campos de tracking mejorado
            $table->unsignedBigInteger('total_lines')->default(0)->change();
            $table->unsignedBigInteger('valid_lines')->default(0);
            $table->unsignedBigInteger('skipped_lines')->default(0);
            $table->unsignedBigInteger('warning_lines')->default(0);

            // Tracking de inserción
            $table->unsignedBigInteger('updated_records')->default(0);
            $table->unsignedBigInteger('duplicate_records')->default(0)->comment('Duplicados dentro del archivo');
            $table->unsignedBigInteger('skipped_records')->default(0)->comment('Registros omitidos por configuración');

            // Timing mejorado
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Checkpointing mejorado
            $table->unsignedBigInteger('checkpoint_line')->default(0);
            $table->unsignedBigInteger('checkpoint_byte_offset')->default(0);
            $table->timestamp('checkpoint_timestamp')->nullable();

            // Configuración de merge
            $table->string('merge_strategy', 30)->default('insert')->comment('insert|insert_update|replace');
            $table->boolean('skip_duplicates')->default(true)->comment('¿Saltar duplicados en archivo?');
            $table->boolean('skip_unknown_ubigeo')->default(false)->comment('¿Saltar UBIGEO desconocidos?');
            $table->integer('max_errors_allowed')->nullable()->comment('Máx errores antes de abortar (null = sin límite)');

            // Rollback tracking
            $table->timestamp('rollback_requested_at')->nullable();
            $table->timestamp('rollback_started_at')->nullable();
            $table->timestamp('rollback_completed_at')->nullable();
            $table->text('rollback_reason')->nullable();

            // Status y mensajes mejorados
            $table->text('last_error')->nullable()->comment('Últimos 500 chars del error');
            $table->text('last_warning')->nullable()->comment('Última advertencia');
            $table->text('status_message')->nullable()->comment('Mensaje de estado actual');

            // Performance metrics
            $table->integer('memory_peak_mb')->nullable()->comment('Pico de memoria usado');
            $table->integer('duration_seconds')->nullable()->comment('Duración total en segundos');
            $table->decimal('lines_per_second', 10, 2)->nullable()->comment('Velocidad promedio');
            $table->integer('estimated_time_left')->nullable()->comment('ETA en segundos');

            // Índices nuevos
            $table->index(['status', 'created_at']);
            $table->index(['created_by', 'created_at']);
            $table->index(['checkpoint_line', 'checkpoint_byte_offset']);
        });

        // Tabla: ruc_import_events (Event Sourcing)
        Schema::create('ruc_import_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ruc_import_id')->constrained('ruc_imports')->cascadeOnDelete();

            $table->string('event_type', 50)->index();
            // Tipos de eventos:
            // - import.started
            // - import.checkpoint
            // - import.paused
            // - import.resumed
            // - import.cancelled
            // - import.completed
            // - import.failed
            // - import.rollback_requested
            // - import.rollback_started
            // - import.rollback_completed

            $table->jsonb('data')->nullable()->comment('Datos del evento');
            // Ejemplo: {
            //   "line_processed": 1000,
            //   "records_inserted": 950,
            //   "errors": 50,
            //   "memory_mb": 128,
            //   "duration_ms": 5000,
            //   "byte_offset": 102400,
            //   "speed": 1200.5,
            //   "eta_seconds": 300
            // }

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['ruc_import_id', 'created_at']);
            $table->index('event_type');
        });

        // Tabla: ruc_import_duplicates (Rastrear duplicados)
        Schema::create('ruc_import_duplicates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ruc_import_id')->constrained('ruc_imports')->cascadeOnDelete();

            $table->string('ruc', 11);
            $table->unsignedBigInteger('first_line')->comment('Línea de primer registro');
            $table->unsignedBigInteger('duplicate_line')->comment('Línea de duplicado');

            $table->string('action', 30)->default('skipped')->comment('skipped|kept_first|kept_latest');

            $table->timestamp('created_at')->useCurrent();

            $table->index('ruc_import_id');
            $table->index('ruc');
            $table->unique(['ruc_import_id', 'ruc', 'duplicate_line']);
        });

        // Actualizar tabla ruc_import_errors con nuevos campos
        Schema::table('ruc_import_errors', function (Blueprint $table): void {
            $table->string('error_code', 50)->nullable()->after('reason')->comment('INVALID_RUC, EMPTY_RAZON_SOCIAL, etc');
            $table->string('error_category', 30)->nullable()->after('error_code')->comment('validation|duplicate|system');
            $table->boolean('resolved')->default(false)->after('error_category')->comment('¿Resuelto manualmente?');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();

            $table->index(['ruc_import_id', 'error_category']);
            $table->index(['error_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruc_import_duplicates');
        Schema::dropIfExists('ruc_import_events');

        Schema::table('ruc_import_errors', function (Blueprint $table): void {
            $table->dropForeignIdFor('resolved_by');
            $table->dropColumn([
                'error_code',
                'error_category',
                'resolved',
                'resolved_by',
                'resolution_notes',
            ]);
        });

        Schema::table('ruc_imports', function (Blueprint $table): void {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['created_by', 'created_at']);
            $table->dropIndex(['checkpoint_line', 'checkpoint_byte_offset']);

            $table->dropColumn([
                'valid_lines',
                'skipped_lines',
                'warning_lines',
                'updated_records',
                'duplicate_records',
                'skipped_records',
                'paused_at',
                'cancelled_at',
                'checkpoint_line',
                'checkpoint_byte_offset',
                'checkpoint_timestamp',
                'merge_strategy',
                'skip_duplicates',
                'skip_unknown_ubigeo',
                'max_errors_allowed',
                'rollback_requested_at',
                'rollback_started_at',
                'rollback_completed_at',
                'rollback_reason',
                'last_error',
                'last_warning',
                'status_message',
                'memory_peak_mb',
                'duration_seconds',
                'lines_per_second',
                'estimated_time_left',
            ]);
        });
    }
};
