<?php

namespace App\Modules\Ruc\Jobs;

use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Support\RucPadronParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessRucImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout;

    public function __construct(public int $importId)
    {
        $this->timeout = max(300, (int) config('ruc.import_timeout'));
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(RucPadronParser $parser): void
    {
        $import = RucImport::query()->findOrFail($this->importId);
        $lock = Cache::lock('ruc-import-process:'.$import->id, max(600, (int) config('ruc.import_lock_seconds')));
        if (! $lock->get()) {
            $import->update(['last_message' => 'Esperando el lock exclusivo de esta importación.']);
            $this->release(60);

            return;
        }
        try {
            $this->process($import, $parser);
        } catch (Throwable $exception) {
            $this->markFailed($import, $exception);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function process(RucImport $import, RucPadronParser $parser): void
    {
        if ($import->cancel_requested_at !== null || $import->status === RucImportStatus::Cancelled) {
            $this->markCancelled($import);

            return;
        }

        $disk = Storage::disk($import->disk);
        $exists = $disk->exists($import->path);
        Log::info('Iniciando importación RUC', [
            'import_id' => $import->id,
            'disk' => $import->disk,
            'path' => $import->path,
            'exists' => $exists,
            'size' => $exists ? $disk->size($import->path) : null,
        ]);
        if (! $exists) {
            throw new RuntimeException("No existe el archivo de importación en el disk [{$import->disk}] y path [{$import->path}].");
        }

        $stream = $disk->readStream($import->path);
        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo abrir el stream privado de la importación RUC.');
        }

        $import->update([
            'status' => RucImportStatus::Validating,
            'started_at' => $import->started_at ?? now(),
            'failed_at' => null,
            'error_message' => null,
            'job_uuid' => $this->job?->uuid(),
            'queue_name' => (string) config('ruc.import_queue'),
            'last_message' => 'Validando y contando líneas del archivo.',
            'last_heartbeat_at' => now(),
        ]);

        try {
            $total = $this->countLines($stream);
            if ($total === 0) {
                throw new RuntimeException('El archivo está vacío.');
            }
            if (! rewind($stream)) {
                throw new RuntimeException('No se pudo rebobinar el stream después de contar las líneas.');
            }

            $chunkSize = min(3000, max(100, (int) config('ruc.import_chunk_size')));
            $progressInterval = min($chunkSize, max(1, (int) config('ruc.import_progress_interval')));
            $import->update([
                'total_rows' => $total,
                'total_chunks' => (int) ceil($total / $progressInterval),
                'status' => RucImportStatus::Processing,
                'last_message' => 'Procesando filas del padrón.',
                'last_heartbeat_at' => now(),
            ]);

            $batch = $errors = [];
            $processed = $inserted = $ignored = $invalid = $chunk = 0;
            while (($line = fgets($stream)) !== false) {
                $processed++;
                $parsed = $parser->parse($line, $import->delimiter, $import->encoding);
                if (isset($parsed['header'])) {
                    $ignored++;
                } elseif (isset($parsed['error'])) {
                    $invalid++;
                    $errors[] = ['ruc_import_id' => $import->id, 'line_number' => $processed, 'reason' => $parsed['error'], 'line_preview' => $parser->preview($line, $import->encoding), 'created_at' => now()];
                } else {
                    $batch[] = $parsed['data'];
                }
                if (($processed % $progressInterval) === 0) {
                    [$added, $duplicates] = $this->flush($batch, $errors);
                    $inserted += $added;
                    $ignored += $duplicates;
                    $chunk++;
                    $this->progress($import, $processed, $inserted, $ignored, $invalid, $chunk, $total);
                    $batch = $errors = [];
                    if ($import->fresh()->cancel_requested_at !== null) {
                        $this->markCancelled($import);

                        return;
                    }
                }
            }
            [$added, $duplicates] = $this->flush($batch, $errors);
            $inserted += $added;
            $ignored += $duplicates;
            $chunk += ($batch !== [] || $errors !== []) ? 1 : 0;
            $status = $invalid > 0 ? RucImportStatus::CompletedWithErrors : RucImportStatus::Completed;
            $import->update([
                'status' => $status,
                'processed_rows' => $processed,
                'inserted_rows' => $inserted,
                'ignored_rows' => $ignored,
                'invalid_rows' => $invalid,
                'progress_percentage' => 100,
                'current_chunk' => $chunk,
                'finished_at' => now(),
                'last_message' => $invalid > 0 ? 'Importación completada con filas inválidas.' : 'Importación completada correctamente.',
                'last_heartbeat_at' => now(),
            ]);
        } finally {
            fclose($stream);
        }
    }

    public function failed(Throwable $exception): void
    {
        $import = RucImport::query()->find($this->importId);
        if ($import !== null) {
            $this->markFailed($import, $exception);
        }
        report($exception);
    }

    private function flush(array $batch, array $errors): array
    {
        $inserted = $batch === [] ? 0 : $this->insertReturningCount($batch);
        if ($errors !== []) {
            DB::table('ruc_import_errors')->insert($errors);
        }

        return [$inserted, count($batch) - $inserted];
    }

    private function insertReturningCount(array $batch): int
    {
        $columns = array_keys($batch[0]);
        $columnSql = implode(', ', array_map(fn (string $column): string => '"'.$column.'"', $columns));
        $rowSql = '('.implode(', ', array_fill(0, count($columns), '?')).')';
        $bindings = [];
        foreach ($batch as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column];
            }
        }
        $sql = 'INSERT INTO "ruc_records" ('.$columnSql.') VALUES '
            .implode(', ', array_fill(0, count($batch), $rowSql))
            .' ON CONFLICT ("ruc") DO NOTHING RETURNING "ruc"';

        return count(DB::select($sql, $bindings));
    }

    private function progress(RucImport $import, int $processed, int $inserted, int $ignored, int $invalid, int $chunk, int $total): void
    {
        $import->update(['processed_rows' => $processed, 'inserted_rows' => $inserted, 'ignored_rows' => $ignored, 'invalid_rows' => $invalid, 'progress_percentage' => min(99.99, round($processed * 100 / max(1, $total), 2)), 'current_chunk' => $chunk, 'last_message' => "Lote {$chunk} confirmado.", 'last_heartbeat_at' => now()]);
    }

    private function countLines($stream): int
    {
        $count = 0;
        while (fgets($stream) !== false) {
            $count++;
        }

        return $count;
    }

    private function markCancelled(RucImport $import): void
    {
        $import->update([
            'status' => RucImportStatus::Cancelled,
            'finished_at' => now(),
            'last_heartbeat_at' => now(),
            'last_message' => 'Importación cancelada por solicitud administrativa.',
        ]);
    }

    private function markFailed(RucImport $import, Throwable $exception): void
    {
        $message = Str::limit($exception->getMessage(), 2000);
        $import->forceFill([
            'status' => RucImportStatus::Failed,
            'failed_at' => now(),
            'error_message' => $message,
            'last_message' => 'La importación falló y el worker detuvo el procesamiento.',
            'last_heartbeat_at' => now(),
        ])->save();
        Log::error('Falló la importación RUC', ['import_id' => $import->id, 'exception' => $exception]);
    }
}
