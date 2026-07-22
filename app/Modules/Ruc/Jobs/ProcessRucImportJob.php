<?php

namespace App\Modules\Ruc\Jobs;

use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Models\Ubigeo;
use App\Modules\Ruc\Services\RucCopyLoader;
use App\Modules\Ruc\Services\RucIncomingFileScanner;
use App\Modules\Ruc\Services\RucMergeService;
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

    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $importId)
    {
        $this->timeout = max(300, (int) config('ruc.import.timeout'));
        $this->onQueue((string) config('ruc.import.queue'));
    }

    public function handle(RucPadronParser $parser, RucCopyLoader $copy, RucMergeService $merge, RucIncomingFileScanner $scanner): void
    {
        $import = RucImport::query()->findOrFail($this->importId);
        $lock = Cache::lock('ruc-import-process:'.$import->id, max(600, (int) config('ruc.import.lock_seconds')));
        if (! $lock->get()) {
            $this->release(60);

            return;
        }
        try {
            $this->process($import, $parser, $copy, $merge, $scanner);
        } catch (Throwable $exception) {
            $this->markFailed($import, $exception);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function process(RucImport $import, RucPadronParser $parser, RucCopyLoader $copy, RucMergeService $merge, RucIncomingFileScanner $scanner): void
    {
        $disk = Storage::disk($import->disk);
        if (! $disk->exists($import->path)) {
            throw new RuntimeException("No existe el archivo RUC en disk [{$import->disk}] y path [{$import->path}].");
        }
        if (config('ruc.import.validate_checksum') && hash_file('sha256', $disk->path($import->path)) !== $import->file_hash) {
            throw new RuntimeException('El archivo RUC cambió desde que fue registrado; no es seguro reanudarlo.');
        }
        $stream = $disk->readStream($import->path);
        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo abrir el stream privado RUC.');
        }
        $started = microtime(true);
        try {
            $total = $import->total_rows;
            if ($total < 1) {
                $import->update(['status' => RucImportStatus::Validating, 'started_at' => now(), 'last_heartbeat_at' => now(), 'last_message' => 'Contando líneas del padrón SUNAT.']);
                $total = $this->countLines($stream);
                if ($total < 1 || ! rewind($stream)) {
                    throw new RuntimeException('El TXT RUC está vacío o no se pudo rebobinar.');
                }
                $import->update(['total_rows' => $total]);
            } elseif ($import->current_byte_offset > 0 && fseek($stream, $import->current_byte_offset) !== 0) {
                throw new RuntimeException('No se pudo reanudar el TXT RUC desde el checkpoint guardado.');
            }
            $import->update(['status' => RucImportStatus::Processing, 'job_uuid' => $this->job?->uuid(), 'queue_name' => config('ruc.import.queue'), 'last_message' => 'Procesando padrón SUNAT mediante staging y COPY.', 'last_heartbeat_at' => now()]);
            $ubigeos = Ubigeo::query()->get(['codigo', 'departamento', 'provincia', 'distrito'])->keyBy('codigo');
            $rows = $errors = [];
            $line = (int) $import->current_line_number;
            $valid = (int) DB::table('ruc_staging')->where('import_id', $import->id)->count();
            $invalid = (int) $import->invalid_rows;
            $resolved = (int) $import->resolved_ubigeo_rows;
            $unknown = (int) $import->unknown_ubigeo_rows;
            $addresses = (int) $import->address_rows;
            $chunk = (int) $import->last_completed_chunk;
            $chunkSize = max(100, (int) config('ruc.import.chunk_size'));
            while (($raw = fgets($stream)) !== false) {
                $line++;
                $parsed = $parser->parse($raw, $import->delimiter, $import->encoding);
                if (isset($parsed['header'])) {
                    continue;
                }
                if (isset($parsed['error'])) {
                    $invalid++;
                    $errors[] = ['ruc_import_id' => $import->id, 'line_number' => $line, 'reason' => $parsed['error'], 'line_preview' => $parser->preview($raw, $import->encoding), 'created_at' => now()];
                } else {
                    $data = $parsed['data'];
                    if ($data['ubigeo'] !== null && ($location = $ubigeos->get($data['ubigeo'])) !== null) {
                        $data['departamento'] = $location->departamento;
                        $data['provincia'] = $location->provincia;
                        $data['distrito'] = $location->distrito;
                        $resolved++;
                    } elseif ($data['ubigeo'] !== null) {
                        $unknown++;
                    }
                    $addresses += $data['direccion'] !== null ? 1 : 0;
                    $data['row_number'] = $line;
                    $rows[] = $data;
                    $valid++;
                }
                if (count($rows) + count($errors) >= $chunkSize) {
                    $chunk++;
                    $this->checkpoint($import, $copy, $rows, $errors, $line, ftell($stream), $valid, $invalid, $resolved, $unknown, $addresses, $chunk, $total, $started);
                    $rows = $errors = [];
                    $fresh = $import->fresh();
                    if ($fresh->cancel_requested_at !== null) {
                        $fresh->update(['status' => RucImportStatus::Cancelled, 'finished_at' => now(), 'last_heartbeat_at' => now()]);

                        return;
                    }
                    if ($fresh->status === RucImportStatus::Paused) {
                        return;
                    }
                }
            }
            if ($rows !== [] || $errors !== []) {
                $chunk++;
                $this->checkpoint($import, $copy, $rows, $errors, $line, ftell($stream), $valid, $invalid, $resolved, $unknown, $addresses, $chunk, $total, $started);
            }
            $counts = DB::transaction(fn (): array => $merge->merge($import));
            DB::table('ruc_staging')->where('import_id', $import->id)->delete();
            $import->update([
                'status' => $invalid > 0 ? RucImportStatus::CompletedWithErrors : RucImportStatus::Completed,
                'processed_rows' => $line, 'inserted_rows' => $counts['inserted'], 'ignored_rows' => $counts['ignored'],
                'invalid_rows' => $invalid, 'resolved_ubigeo_rows' => $resolved, 'unknown_ubigeo_rows' => $unknown,
                'address_rows' => $addresses, 'progress_percentage' => 100, 'finished_at' => now(),
                'last_heartbeat_at' => now(), 'last_message' => 'Importación RUC completada.',
            ]);
            if (config('ruc.import.archive_files')) {
                $archiveDirectory = $scanner->resolveDirectory((string) config('ruc.import.archive_directory'), $disk);
                $archive = $archiveDirectory.'/'.$import->uuid.'-'.basename($import->path);
                $disk->makeDirectory($archiveDirectory);
                if ($disk->move($import->path, $archive)) {
                    $import->update(['archive_path' => $archive]);
                }
            }
        } finally {
            fclose($stream);
        }
    }

    private function checkpoint(RucImport $import, RucCopyLoader $copy, array $rows, array $errors, int $line, int|false $offset, int $valid, int $invalid, int $resolved, int $unknown, int $addresses, int $chunk, int $total, float $started): void
    {
        if ($offset === false) {
            throw new RuntimeException('No se pudo guardar el byte offset del TXT RUC.');
        }
        DB::transaction(function () use ($import, $copy, $rows, $errors, $line, $offset, $invalid, $resolved, $unknown, $addresses, $chunk, $total, $started): void {
            $copy->load($import->id, $rows);
            if ($errors !== []) {
                DB::table('ruc_import_errors')->insert($errors);
            }
            $elapsed = max(.001, microtime(true) - $started);
            $speed = max(0, ($line - $import->current_line_number) / $elapsed);
            $eta = $speed > 0 ? (int) max(0, ($total - $line) / $speed) : null;
            $import->update([
                'processed_rows' => $line, 'invalid_rows' => $invalid, 'resolved_ubigeo_rows' => $resolved,
                'unknown_ubigeo_rows' => $unknown, 'address_rows' => $addresses, 'current_byte_offset' => $offset,
                'current_line_number' => $line, 'last_completed_chunk' => $chunk, 'current_chunk' => $chunk,
                'progress_percentage' => min(99.99, round($line * 100 / max(1, $total), 2)),
                'last_message' => sprintf('Lote %d confirmado · %.1f filas/s · ETA %s', $chunk, $speed, $eta === null ? 'calculando' : gmdate('H:i:s', $eta)),
                'last_heartbeat_at' => now(),
            ]);
        });
    }

    private function countLines($stream): int
    {
        $count = 0;
        while (fgets($stream) !== false) {
            $count++;
        }

        return $count;
    }

    private function markFailed(RucImport $import, Throwable $exception): void
    {
        $import->forceFill(['status' => RucImportStatus::Failed, 'failed_at' => now(), 'error_message' => Str::limit($exception->getMessage(), 2000), 'last_message' => 'La importación RUC falló.', 'last_heartbeat_at' => now()])->save();
        Log::error('Falló la importación RUC', ['import_id' => $import->id, 'exception' => $exception]);
    }

    public function failed(Throwable $exception): void
    {
        if (($import = RucImport::query()->find($this->importId)) !== null) {
            $this->markFailed($import, $exception);
        }
    }
}
