<?php

namespace App\Modules\Reniec\Jobs;

use App\Modules\Reniec\Enums\ReniecImportStatus;
use App\Modules\Reniec\Models\ReniecImport;
use App\Modules\Reniec\Services\ReniecCopyLoader;
use App\Modules\Reniec\Services\ReniecFileService;
use App\Modules\Reniec\Services\ReniecIncomingFileScanner;
use App\Modules\Reniec\Services\ReniecMergeService;
use App\Modules\Reniec\Support\ReniecLineParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessReniecImportJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 1;

    public int $timeout = 86400;

    public function __construct(public readonly int $importId)
    {
        $this->onConnection('redis');
        $this->onQueue((string) config('reniec.import.queue'));
    }

    public function handle(ReniecFileService $files, ReniecLineParser $parser, ReniecCopyLoader $copy, ReniecMergeService $merge): void
    {
        $import = ReniecImport::query()->findOrFail($this->importId);
        $lock = Cache::lock('reniec-import-global', (int) config('reniec.import.lock_seconds'));
        if (! $lock->get()) {
            throw new \RuntimeException('Ya existe una importación RENIEC activa.');
        }
        try {
            $this->process($import, $files, $parser, $copy, $merge);
        } catch (Throwable $e) {
            $import->refresh()->update(['status' => ReniecImportStatus::Failed, 'failed_at' => now(), 'last_heartbeat_at' => now(), 'error_message' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    private function process(ReniecImport $import, ReniecFileService $files, ReniecLineParser $parser, ReniecCopyLoader $copy, ReniecMergeService $merge): void
    {
        $path = $files->assertUnchanged($import);
        $stream = fopen($path, 'rb');
        if (! is_resource($stream)) {
            throw new \RuntimeException('No se pudo abrir el archivo RENIEC.');
        }
        if ($import->current_byte_offset > 0 && fseek($stream, $import->current_byte_offset) !== 0) {
            throw new \RuntimeException('El checkpoint de bytes no es válido.');
        }
        $started = microtime(true);
        $import->update(['status' => ReniecImportStatus::Processing, 'started_at' => $import->started_at ?: now(), 'resumed_at' => $import->current_byte_offset > 0 ? now() : null, 'last_heartbeat_at' => now()]);
        $import->update(['metadata' => array_merge($import->metadata ?? [], ['host' => gethostname() ?: 'unknown', 'worker_pid' => getmypid(), 'queue' => (string) config('reniec.import.queue'), 'app_commit' => trim((string) shell_exec('git rev-parse --short HEAD 2>/dev/null')) ?: null])]);
        $rows = [];
        $errors = [];
        $line = (int) $import->current_line_number;
        $valid = (int) $import->valid_rows;
        $invalid = (int) $import->invalid_rows;
        $chunk = (int) $import->last_completed_chunk;
        while (($raw = fgets($stream)) !== false) {
            $line++;
            $parsed = $parser->parse($raw, $line, (string) config('reniec.import.delimiter'), (string) config('reniec.import.encoding'));
            if (isset($parsed['header'])) {
                continue;
            }if (isset($parsed['data'])) {
                $valid++;
                $rows[] = ['row_number' => $line] + $parsed['data'];
            } else {
                $invalid++;
                $code = $parsed['error'] ?? 'parser_exception';
                $errors[] = [$line, $code, $code, hash('sha256', $raw), 'Contenido omitido'];
            }
            if (count($rows) + count($errors) >= (int) config('reniec.import.chunk_size')) {
                $chunk++;
                $this->checkpoint($import, $copy, $rows, $errors, $line, ftell($stream), $valid, $invalid, $chunk, $started);
                $rows = [];
                $errors = [];
                $import->refresh();
                if ($import->cancel_requested_at) {
                    $import->update(['status' => ReniecImportStatus::Cancelled, 'cancelled_at' => now(), 'last_heartbeat_at' => now()]);
                    fclose($stream);

                    return;
                }if ($import->paused_at) {
                    $import->update(['status' => ReniecImportStatus::Paused, 'last_heartbeat_at' => now()]);
                    fclose($stream);

                    return;
                }
            }
        }
        if ($rows !== [] || $errors !== []) {
            $chunk++;
            $this->checkpoint($import, $copy, $rows, $errors, $line, ftell($stream), $valid, $invalid, $chunk, $started);
        }fclose($stream);
        $import->refresh()->update(['status' => ReniecImportStatus::Merging, 'last_heartbeat_at' => now()]);
        $duplicateRows = (int) DB::query()->fromSub(DB::table('reniec_import_staging')->select('dni')->selectRaw('count(*) AS aggregate')->where('import_id', $import->id)->groupBy('dni')->havingRaw('count(*) > 1'), 'duplicates')->sum(DB::raw('aggregate - 1'));
        $import->update(['duplicate_rows' => $duplicateRows]);
        $counts = DB::transaction(fn () => $merge->merge($import));
        $import->update(['status' => ReniecImportStatus::Analyzing, 'inserted_rows' => $counts['inserted'], 'updated_rows' => $counts['updated'], 'ignored_rows' => $counts['ignored'], 'last_heartbeat_at' => now()]);
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ANALYZE dni_records');
        }$processed = $valid + $invalid;
        if ($processed > $line) {
            throw new \RuntimeException('Los contadores de integridad no cuadran con las líneas procesadas.');
        }
        $status = $invalid > 0 ? ReniecImportStatus::CompletedWithErrors : ReniecImportStatus::Completed;
        $import->update(['status' => $status, 'total_rows' => $processed, 'processed_rows' => $processed, 'finished_at' => now(), 'last_heartbeat_at' => now(), 'estimated_seconds_remaining' => 0]);
        if (config('reniec.import.archive_files')) {
            $archive = app(ReniecIncomingFileScanner::class)->resolveDirectory((string) config('reniec.import.archive_directory'), Storage::disk($import->disk)).'/'.$import->uuid.'-'.basename($import->source_path);
            if (Storage::disk($import->disk)->move($import->source_path, $archive)) {
                $import->update(['archive_path' => $archive]);
            }
        }
    }

    private function checkpoint(ReniecImport $import, ReniecCopyLoader $copy, array $rows, array $errors, int $line, int|false $offset, int $valid, int $invalid, int $chunk, float $started): void
    {
        if ($offset === false) {
            throw new \RuntimeException('No se pudo obtener el byte offset.');
        }DB::transaction(function () use ($import, $copy, $rows, $line, $offset, $valid, $invalid, $chunk, $started) {
            $copy->load($import->id, $rows);
            $elapsed = max(0.001, microtime(true) - $started);
            $speed = ($valid + $invalid) / $elapsed;
            $fraction = $import->file_size > 0 ? min(1, $offset / $import->file_size) : 0;
            $estimatedTotal = $fraction > 0 ? (int) round(($valid + $invalid) / $fraction) : 0;
            $eta = $speed > 0 && $estimatedTotal > 0 ? (int) max(0, round(($estimatedTotal - ($valid + $invalid)) / $speed)) : null;
            $import->update(['processed_rows' => $valid + $invalid, 'valid_rows' => $valid, 'invalid_rows' => $invalid, 'current_byte_offset' => $offset, 'current_line_number' => $line, 'last_completed_chunk' => $chunk, 'rows_per_second' => round($speed, 2), 'estimated_seconds_remaining' => $eta, 'last_heartbeat_at' => now()]);
        });
        if ($errors !== []) {
            $path = app(ReniecIncomingFileScanner::class)->resolveDirectory((string) config('reniec.import.errors_directory'), Storage::disk($import->disk)).'/'.$import->uuid.'.csv';
            if (! Storage::disk($import->disk)->exists($path)) {
                Storage::disk($import->disk)->put($path, "line_number,error_code,error_message,raw_value_hash,safe_excerpt\n");
            }
            $text = collect($errors)->map(fn ($e) => implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $e)))->implode("\n")."\n";
            Storage::disk($import->disk)->append($path, $text);
            $import->update(['error_file_path' => $path]);
        }
    }

    public function failed(?Throwable $e): void
    {
        ReniecImport::query()->whereKey($this->importId)->update(['status' => ReniecImportStatus::Failed->value, 'failed_at' => now(), 'last_heartbeat_at' => now(), 'error_message' => mb_substr($e?->getMessage() ?? 'Fallo de worker RENIEC.', 0, 2000)]);
    }
}
