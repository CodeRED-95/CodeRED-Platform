<?php

namespace App\Modules\Ruc\Jobs;

use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Services\RucFileHasher;
use App\Modules\Ruc\Services\RucIncomingFileValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PrepareRucImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public function __construct(public readonly int $importId)
    {
        $this->timeout = max(300, (int) config('ruc.import.timeout'));
        $this->onQueue((string) config('ruc.import.queue'));
    }

    public function handle(RucIncomingFileValidator $validator, RucFileHasher $hasher): void
    {
        $import = RucImport::query()->findOrFail($this->importId);
        try {
            $result = $validator->validate($import->path);
            if (! $result['valid']) {
                throw new \RuntimeException($result['message']);
            }
            $hash = $hasher->sha256($import->disk, $import->path);
            if (RucImport::query()->whereKeyNot($import->id)->where('file_hash', $hash)->exists()) {
                throw new \RuntimeException('Este archivo ya fue registrado anteriormente.');
            }
            $import->update(['file_hash' => $hash, 'encoding' => $result['encoding'], 'delimiter' => $result['delimiter'], 'status' => RucImportStatus::Registered, 'last_message' => 'Archivo validado y hash SHA-256 calculado; listo para iniciar.']);
            Log::info('Preparación de archivo RUC finalizada', ['import_id' => $import->id, 'path' => $import->path]);
        } catch (Throwable $exception) {
            report($exception);
            $import->update(['status' => RucImportStatus::Failed, 'failed_at' => now(), 'error_message' => $exception->getMessage(), 'last_message' => 'Falló la preparación del archivo.']);
            Log::error('Falló la preparación del archivo RUC', ['import_id' => $import->id, 'path' => $import->path, 'exception' => $exception::class, 'message' => $exception->getMessage()]);
        }
    }
}
