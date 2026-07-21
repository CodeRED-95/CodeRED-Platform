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
use Illuminate\Support\Facades\Storage;
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
        $lock = Cache::lock('ruc-import:active', max(600, (int) config('ruc.import_lock_seconds')));
        if (! $lock->get()) {
            $this->release(60);

            return;
        }
        try {
            $import = RucImport::query()->findOrFail($this->importId);
            $path = Storage::disk($import->disk)->path($import->path);
            $import->update(['status' => RucImportStatus::Validating, 'started_at' => now(), 'last_heartbeat_at' => now()]);
            $total = $this->countLines($path);
            if ($total === 0) {
                throw new \RuntimeException('El archivo está vacío.');
            }
            $chunkSize = max(100, (int) config('ruc.import_chunk_size'));
            $import->update(['total_rows' => $total, 'total_chunks' => (int) ceil($total / $chunkSize), 'status' => RucImportStatus::Processing]);
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                throw new \RuntimeException('No se pudo abrir el padrón almacenado.');
            }
            $batch = $errors = [];
            $processed = $inserted = $ignored = $invalid = $chunk = 0;
            while (($line = fgets($handle)) !== false) {
                $processed++;
                $parsed = $parser->parse($line, $import->delimiter, $import->encoding);
                if (isset($parsed['header'])) {
                    $ignored++;
                } elseif (isset($parsed['error'])) {
                    $invalid++;
                    $errors[] = ['ruc_import_id' => $import->id, 'line_number' => $processed, 'reason' => $parsed['error'], 'line_preview' => mb_substr(mb_convert_encoding(trim($line), 'UTF-8', $import->encoding), 0, 300), 'created_at' => now()];
                } else {
                    $batch[] = $parsed['data'];
                }
                if (count($batch) + count($errors) >= $chunkSize) {
                    [$added, $duplicates] = $this->flush($batch, $errors);
                    $inserted += $added;
                    $ignored += $duplicates;
                    $chunk++;
                    $this->progress($import, $processed, $inserted, $ignored, $invalid, $chunk, $total);
                    $batch = $errors = [];
                    if ($import->fresh()->status === RucImportStatus::Cancelled) {
                        fclose($handle);

                        return;
                    }
                }
            }
            fclose($handle);
            [$added, $duplicates] = $this->flush($batch, $errors);
            $inserted += $added;
            $ignored += $duplicates;
            $status = $invalid > 0 ? RucImportStatus::CompletedWithErrors : RucImportStatus::Completed;
            $import->update(['status' => $status, 'processed_rows' => $processed, 'inserted_rows' => $inserted, 'ignored_rows' => $ignored, 'invalid_rows' => $invalid, 'progress_percentage' => 100, 'finished_at' => now(), 'last_heartbeat_at' => now()]);
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        RucImport::query()->whereKey($this->importId)->update(['status' => RucImportStatus::Failed->value, 'failed_at' => now(), 'last_heartbeat_at' => now(), 'error_message' => 'La importación falló. Revisa el log seguro del worker.']);
        report($exception);
    }

    private function flush(array $batch, array $errors): array
    {
        $inserted = $batch === [] ? 0 : DB::table('ruc_records')->insertOrIgnore($batch);
        if ($errors !== []) {
            DB::table('ruc_import_errors')->insert($errors);
        }

        return [$inserted, count($batch) - $inserted];
    }

    private function progress(RucImport $import, int $processed, int $inserted, int $ignored, int $invalid, int $chunk, int $total): void
    {
        $import->update(['processed_rows' => $processed, 'inserted_rows' => $inserted, 'ignored_rows' => $ignored, 'invalid_rows' => $invalid, 'progress_percentage' => min(99.99, round($processed * 100 / max(1, $total), 2)), 'current_chunk' => $chunk, 'last_heartbeat_at' => now()]);
    }

    private function countLines(string $path): int
    {
        $count = 0;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }
        while (fgets($handle) !== false) {
            $count++;
        }
        fclose($handle);

        return $count;
    }
}
