<?php

namespace App\Modules\Ruc\Services;

use App\Modules\Ruc\Data\ProgressCheckpoint;
use App\Modules\Ruc\Models\RucImport;
use Illuminate\Support\Facades\DB;

class RucProgressTracker
{
    /**
     * Registra un checkpoint en la BD
     */
    public function checkpoint(RucImport $import, ProgressCheckpoint $checkpoint): void
    {
        $import->update([
            'checkpoint_line' => $checkpoint->linesProcessed,
            'checkpoint_byte_offset' => $checkpoint->byteOffset,
            'checkpoint_timestamp' => now(),
            'processed_lines' => $checkpoint->linesProcessed,
            'inserted_records' => $checkpoint->recordsInserted,
            'invalid_rows' => $checkpoint->errorCount,
            'valid_lines' => $checkpoint->recordsInserted + $checkpoint->errorCount,
            'progress_percentage' => $checkpoint->progressPercentage,
            'lines_per_second' => $checkpoint->linesPerSecond,
            'estimated_time_left' => $checkpoint->estimatedTimeLeft,
            'memory_peak_mb' => $checkpoint->memoryUsedMb,
            'status_message' => $checkpoint->message,
            'last_heartbeat_at' => now(),
        ]);

        // Registrar evento
        $import->recordEvent('import.checkpoint', [
            'line_processed' => $checkpoint->linesProcessed,
            'records_inserted' => $checkpoint->recordsInserted,
            'errors' => $checkpoint->errorCount,
            'memory_mb' => $checkpoint->memoryUsedMb,
            'duration_ms' => $checkpoint->elapsedMilliseconds,
            'byte_offset' => $checkpoint->byteOffset,
            'speed' => $checkpoint->linesPerSecond,
            'eta_seconds' => $checkpoint->estimatedTimeLeft,
            'progress_percentage' => $checkpoint->progressPercentage,
        ]);
    }

    /**
     * Obtiene el último checkpoint de una importación
     */
    public function lastCheckpoint(RucImport $import): ?ProgressCheckpoint
    {
        if ($import->checkpoint_line === 0) {
            return null;
        }

        return new ProgressCheckpoint(
            linesProcessed: $import->checkpoint_line,
            recordsInserted: $import->inserted_records,
            errorCount: $import->invalid_rows,
            byteOffset: $import->checkpoint_byte_offset,
            elapsedSeconds: $import->started_at ? now()->diffInSeconds($import->started_at) : 0,
            linesPerSecond: $import->lines_per_second ?? 0,
            estimatedTimeLeft: $import->estimated_time_left,
            memoryUsedMb: $import->memory_peak_mb ?? 0,
            totalLines: $import->total_lines,
        );
    }

    /**
     * Emite un evento de progreso mediante broadcasting
     */
    public function broadcastProgress(RucImport $import): void
    {
        // Aquí se integra con Laravel Reverb/Pusher si está habilitado
        if (config('ruc.import.broadcasting_enabled', false)) {
            \Illuminate\Support\Facades\Broadcast::event(
                new \App\Modules\Ruc\Events\RucImportProgressUpdated($import)
            );
        }
    }

    /**
     * Obtiene el progreso actual en JSON para API
     */
    public function getProgressJson(RucImport $import): array
    {
        return [
            'import_id' => $import->id,
            'status' => $import->status,
            'progress_percentage' => $import->getProgressPercentage(),
            'lines_processed' => $import->processed_lines,
            'total_lines' => $import->total_lines,
            'records_inserted' => $import->inserted_records,
            'errors' => $import->invalid_rows,
            'duplicates' => $import->duplicate_records,
            'memory_mb' => $import->memory_peak_mb,
            'speed_lines_per_sec' => $import->lines_per_second,
            'estimated_time_left_seconds' => $import->estimated_time_left,
            'status_message' => $import->status_message,
            'started_at' => $import->started_at,
            'last_heartbeat_at' => $import->last_heartbeat_at,
        ];
    }
}
