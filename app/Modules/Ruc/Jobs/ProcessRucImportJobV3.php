<?php

namespace App\Modules\Ruc\Jobs;

use App\Modules\Ruc\Data\ProgressCheckpoint;
use App\Modules\Ruc\Data\ValidationContext;
use App\Modules\Ruc\Enums\RucImportStatusV3;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Services\RucBatchInserter;
use App\Modules\Ruc\Services\RucErrorRecorder;
use App\Modules\Ruc\Services\RucFileStreamReader;
use App\Modules\Ruc\Services\RucLineValidator;
use App\Modules\Ruc\Services\RucProgressTracker;
use App\Modules\Ruc\Services\RucStatisticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SplFileObject;
use Throwable;

class ProcessRucImportJobV3 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout;

    public function __construct(public int $importId)
    {
        $this->timeout = max(300, (int) config('ruc.import.timeout', 3600));
        $this->onQueue((string) config('ruc.import.queue', 'ruc-imports'));
    }

    public function handle(
        RucFileStreamReader $fileReader,
        RucLineValidator $validator,
        RucBatchInserter $inserter,
        RucProgressTracker $progressTracker,
        RucErrorRecorder $errorRecorder
    ): void {
        $import = RucImport::findOrFail($this->importId);

        // Adquirir lock distribuido
        $lock = Cache::lock("ruc-import-process:{$import->id}", 600);
        if (!$lock->get()) {
            $this->release(60);
            return;
        }

        try {
            $this->process($import, $fileReader, $validator, $inserter, $progressTracker, $errorRecorder);
        } catch (Throwable $e) {
            $this->markFailed($import, $e);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    private function process(
        RucImport $import,
        RucFileStreamReader $fileReader,
        RucLineValidator $validator,
        RucBatchInserter $inserter,
        RucProgressTracker $progressTracker,
        RucErrorRecorder $errorRecorder
    ): void {
        $startTime = microtime(true);
        $memoryStart = memory_get_usage(true);

        try {
            // Abrir el archivo
            $file = $fileReader->open($import->path);

            // Validar checksum
            if (config('ruc.import.validate_checksum', true)) {
                if (!$fileReader->validateChecksum($import->path, $import->file_hash)) {
                    throw new RuntimeException('El archivo cambió desde la registración. Checksum inválido.');
                }
            }

            // Calcular total de líneas si no se conoce
            if ($import->total_lines === 0) {
                $import->update(['status' => RucImportStatusV3::Pending->value, 'status_message' => 'Contando líneas del archivo...']);
                $totalLines = $fileReader->countLines($import->path);
                $import->update(['total_lines' => max($totalLines - 1, 0)]); // -1 por header
            } else {
                $totalLines = $import->total_lines + 1; // +1 para la línea de header
            }

            // Reanudar desde checkpoint si existe
            $lineNumber = 1;
            if ($import->checkpoint_line > 0) {
                if (!$fileReader->seekToOffset($file, $import->checkpoint_byte_offset)) {
                    throw new RuntimeException('No se pudo reanudar desde el checkpoint guardado.');
                }
                $lineNumber = $import->checkpoint_line + 1;
            }

            // Preparar contexto de validación
            $context = new ValidationContext();

            // Actualizar status a processing
            $import->update([
                'status' => RucImportStatusV3::Processing->value,
                'job_uuid' => $this->job?->uuid(),
                'queue_name' => config('ruc.import.queue', 'ruc-imports'),
                'started_at' => now(),
                'last_heartbeat_at' => now(),
                'status_message' => 'Procesando archivo...',
            ]);

            // Procesar archivo línea por línea
            $batch = [];
            $batchErrors = [];
            $batchDuplicates = [];
            $batchSize = (int) config('ruc.import.batch_size', 5000);
            $checkpointInterval = (int) config('ruc.import.checkpoint_interval', $batchSize);

            $recordsProcessed = 0;
            $validLines = 0;
            $errorLines = 0;
            $duplicateLines = 0;
            $checkpointCount = 0;

            // Leer línea por línea
            while (($rawLine = $fileReader->readline($file)) !== null) {
                $line = trim($rawLine);

                // Saltar líneas vacías
                if (empty($line)) {
                    $lineNumber++;
                    continue;
                }

                // Parsear línea (split por delimitador)
                $fields = str_getcsv($line, $import->delimiter ?? '|');

                // Saltar header
                if ($lineNumber === 1 || (isset($fields[0]) && strtoupper($fields[0]) === 'RUC')) {
                    $lineNumber++;
                    continue;
                }

                // Validar línea
                $validationResult = $validator->validate($fields, $lineNumber, $context);

                if ($validationResult->isDuplicate) {
                    // Registrar duplicado
                    $duplicateLines++;
                    $batchDuplicates[] = [
                        'ruc' => $validationResult->ruc,
                        'first_line' => $validationResult->firstOccurrence,
                        'duplicate_line' => $lineNumber,
                        'action' => $import->skip_duplicates ? 'skipped' : 'kept_latest',
                    ];
                } elseif ($validationResult->hasErrors()) {
                    // Registrar error
                    $errorLines++;
                    $batchErrors[] = [
                        'line' => $lineNumber,
                        'code' => 'VALIDATION_ERROR',
                        'category' => 'validation',
                        'errors' => $validationResult->errors,
                        'preview' => substr($line, 0, 300),
                    ];
                } else {
                    // Registro válido
                    $validLines++;
                    $batch[] = $validationResult->data;
                }

                $recordsProcessed++;
                $lineNumber++;

                // ¿Procesar batch?
                if (count($batch) + count($batchErrors) >= $batchSize) {
                    $checkpointCount++;
                    $this->processBatch(
                        $import,
                        $batch,
                        $batchErrors,
                        $batchDuplicates,
                        $fileReader,
                        $file,
                        $recordsProcessed,
                        $validLines,
                        $errorLines,
                        $duplicateLines,
                        $totalLines,
                        $startTime,
                        $memoryStart,
                        $progressTracker,
                        $errorRecorder,
                        $inserter
                    );

                    // Limpiar batch
                    $batch = [];
                    $batchErrors = [];
                    $batchDuplicates = [];

                    // Chequear cancelación
                    $freshImport = RucImport::find($import->id);
                    if ($freshImport->cancel_requested_at !== null) {
                        $import->update([
                            'status' => RucImportStatusV3::Cancelled->value,
                            'finished_at' => now(),
                        ]);
                        return;
                    }

                    if ($freshImport->status === RucImportStatusV3::Paused->value) {
                        return;
                    }
                }
            }

            // Procesar último batch
            if (!empty($batch) || !empty($batchErrors) || !empty($batchDuplicates)) {
                $checkpointCount++;
                $this->processBatch(
                    $import,
                    $batch,
                    $batchErrors,
                    $batchDuplicates,
                    $fileReader,
                    $file,
                    $recordsProcessed,
                    $validLines,
                    $errorLines,
                    $duplicateLines,
                    $totalLines,
                    $startTime,
                    $memoryStart,
                    $progressTracker,
                    $errorRecorder,
                    $inserter
                );
            }

            // Cerrar archivo
            $fileReader->close($file);

            // Determinar status final
            $finalStatus = $errorLines === 0
                ? RucImportStatusV3::Completed
                : RucImportStatusV3::CompletedWithErrors;

            // Finalizar
            $elapsedSeconds = max(1, (int) (microtime(true) - $startTime));
            $memoryPeakMb = (int) (memory_get_peak_usage(true) / 1024 / 1024);

            $import->update([
                'status' => $finalStatus->value,
                'processed_lines' => $recordsProcessed,
                'valid_lines' => $validLines,
                'invalid_rows' => $errorLines,
                'progress_percentage' => 100.0,
                'finished_at' => now(),
                'memory_peak_mb' => $memoryPeakMb,
                'duration_seconds' => $elapsedSeconds,
                'lines_per_second' => $recordsProcessed > 0 ? $recordsProcessed / $elapsedSeconds : 0,
                'status_message' => sprintf(
                    'Importación completada. %d líneas procesadas, %d válidas, %d errores, %d duplicados en %ds.',
                    $recordsProcessed,
                    $validLines,
                    $errorLines,
                    $duplicateLines,
                    $elapsedSeconds
                ),
                'last_heartbeat_at' => now(),
            ]);

            $import->recordEvent('import.completed', [
                'records_processed' => $recordsProcessed,
                'records_inserted' => $import->inserted_records,
                'errors' => $errorLines,
                'duplicates' => $duplicateLines,
                'duration_seconds' => $elapsedSeconds,
                'memory_peak_mb' => $memoryPeakMb,
                'speed' => $recordsProcessed > 0 ? $recordsProcessed / $elapsedSeconds : 0,
            ]);

            // Post-import: actualizar estadísticas para query planner (ANALYZE).
            // Después de INSERT masivo, pg_class.n_live_tup puede estar stale,
            // causando planes ineficientes. ANALYZE actualiza estadísticas;
            // es rápido (~2-5s) y no bloquea.
            Log::info('Starting ANALYZE after RUC import', ['import_id' => $import->id]);
            DB::statement('ANALYZE ruc_records');
            Log::info('ANALYZE completed after RUC import', ['import_id' => $import->id]);

            // Actualizar tabla ruc_statistics (usado por dashboard, páginas admin)
            // e invalidar caches para que reflejen el nuevo conteo inmediatamente.
            app(RucStatisticsService::class)->updateAllStatistics('import');
            app(RucStatisticsService::class)->recordAnalyzeComplete();

        } catch (Throwable $e) {
            $fileReader->close($file ?? null);
            throw $e;
        }
    }

    private function processBatch(
        RucImport $import,
        array $batch,
        array $errors,
        array $duplicates,
        RucFileStreamReader $fileReader,
        SplFileObject $file,
        int $recordsProcessed,
        int $validLines,
        int $errorLines,
        int $duplicateLines,
        int $totalLines,
        float $startTime,
        int $memoryStart,
        RucProgressTracker $progressTracker,
        RucErrorRecorder $errorRecorder,
        RucBatchInserter $inserter
    ): void {
        DB::transaction(function () use (
            $import,
            $batch,
            $errors,
            $duplicates,
            $fileReader,
            $file,
            $recordsProcessed,
            $validLines,
            $errorLines,
            $duplicateLines,
            $totalLines,
            $startTime,
            $memoryStart,
            $progressTracker,
            $errorRecorder,
            $inserter
        ): void {
            // Insertar registros
            $insertResult = null;
            if (!empty($batch)) {
                $insertResult = $inserter->insert($import, $batch, $import->merge_strategy);
                $import->inserted_records += $insertResult->inserted;
                $import->duplicate_records += $insertResult->skipped;
            }

            // Registrar errores
            if (!empty($errors)) {
                $errorRecorder->recordErrors($import, $errors);
            }

            // Registrar duplicados
            if (!empty($duplicates)) {
                $errorRecorder->recordDuplicates($import, $duplicates);
            }

            // Checkpoint
            $elapsedSeconds = microtime(true) - $startTime;
            $currentByteOffset = $fileReader->currentOffset($file);
            $memoryUsedMb = (int) ((memory_get_usage(true) - $memoryStart) / 1024 / 1024);
            $linesPerSecond = $recordsProcessed > 0 ? $recordsProcessed / $elapsedSeconds : 0;
            $estimatedTimeLeft = $linesPerSecond > 0
                ? (int) (($totalLines - $recordsProcessed) / $linesPerSecond)
                : null;

            $checkpoint = new ProgressCheckpoint(
                linesProcessed: $recordsProcessed,
                recordsInserted: $import->inserted_records,
                errorCount: $errorLines,
                byteOffset: $currentByteOffset,
                elapsedSeconds: $elapsedSeconds,
                linesPerSecond: $linesPerSecond,
                estimatedTimeLeft: $estimatedTimeLeft,
                memoryUsedMb: $memoryUsedMb,
                totalLines: $totalLines,
                message: sprintf(
                    'Procesadas %d líneas · %.0f líneas/s · ETA %s',
                    $recordsProcessed,
                    $linesPerSecond,
                    $estimatedTimeLeft !== null ? gmdate('H:i:s', $estimatedTimeLeft) : '?'
                ),
                elapsedMilliseconds: (int) ($elapsedSeconds * 1000),
            );

            $progressTracker->checkpoint($import, $checkpoint);
            $progressTracker->broadcastProgress($import);
        });
    }

    private function markFailed(RucImport $import, Throwable $exception): void
    {
        $import->forceFill([
            'status' => RucImportStatusV3::Failed->value,
            'failed_at' => now(),
            'last_error' => substr($exception->getMessage(), 0, 500),
            'status_message' => 'La importación falló. Revise el log de errores.',
            'last_heartbeat_at' => now(),
        ])->save();

        $import->recordEvent('import.failed', [
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        Log::error('Falló importación RUC', [
            'import_id' => $import->id,
            'uuid' => $import->uuid,
            'exception' => $exception->getMessage(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        if (($import = RucImport::find($this->importId)) !== null) {
            $this->markFailed($import, $exception);
        }
    }
}
