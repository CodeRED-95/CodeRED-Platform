<?php

namespace App\Modules\Ruc\Services;

use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Jobs\ProcessRucImportJob;
use App\Modules\Ruc\Models\RucImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RucImportService
{
    public function fromUpload(UploadedFile $file, int $actorId, bool $force = false): RucImport
    {
        return $this->create($file->getRealPath(), $file->getClientOriginalName(), $actorId, $force);
    }

    public function fromPath(string $source, ?int $actorId = null, bool $force = false, bool $dispatch = true): RucImport
    {
        if (! is_file($source) || ! is_readable($source)) {
            throw ValidationException::withMessages(['file' => 'El archivo no existe o no es legible.']);
        }

        return $this->create($source, basename($source), $actorId, $force, $dispatch);
    }

    private function create(string $source, string $originalName, ?int $actorId, bool $force, bool $dispatch = true): RucImport
    {
        $lock = Cache::lock('ruc-import-submit', 15);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['file' => 'Otra solicitud de importación está siendo registrada. Intenta nuevamente.']);
        }

        try {
            return $this->createLocked($source, $originalName, $actorId, $force, $dispatch);
        } finally {
            $lock->release();
        }
    }

    private function createLocked(string $source, string $originalName, ?int $actorId, bool $force, bool $dispatch): RucImport
    {
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'txt' || filesize($source) === 0) {
            throw ValidationException::withMessages(['file' => 'Selecciona un archivo TXT no vacío.']);
        }
        $maximum = max(1, (int) config('ruc.import_max_size_mb')) * 1024 * 1024;
        if (filesize($source) > $maximum) {
            throw ValidationException::withMessages(['file' => 'El archivo supera el tamaño máximo configurado.']);
        }
        if (RucImport::query()->whereIn('status', [RucImportStatus::Pending, RucImportStatus::Queued, RucImportStatus::Validating, RucImportStatus::Processing])->exists()) {
            throw ValidationException::withMessages(['file' => 'Ya existe una importación RUC activa.']);
        }
        $hash = hash_file('sha256', $source);
        if (! $force && RucImport::query()->where('file_hash', $hash)->whereIn('status', [RucImportStatus::Completed, RucImportStatus::CompletedWithErrors])->exists()) {
            throw ValidationException::withMessages(['file' => 'Este archivo ya fue importado. Usa --force para reprocesarlo sin sobrescribir RUC existentes.']);
        }
        $uuid = (string) Str::uuid();
        $stored = $uuid.'.txt';
        $directory = trim((string) config('ruc.import_directory'), '/');
        $disk = (string) config('ruc.import_disk');
        $path = $directory.'/'.$stored;
        $stream = fopen($source, 'rb');
        if (! is_resource($stream) || ! Storage::disk($disk)->writeStream($path, $stream)) {
            throw ValidationException::withMessages(['file' => 'No se pudo guardar el archivo en el almacenamiento privado.']);
        }
        fclose($stream);
        $import = RucImport::query()->create([
            'uuid' => $uuid, 'original_filename' => basename($originalName), 'stored_filename' => $stored,
            'disk' => $disk, 'path' => $path, 'file_size' => filesize($source), 'file_hash' => $hash,
            'status' => RucImportStatus::Queued, 'encoding' => (string) config('ruc.import_encoding'),
            'delimiter' => (string) config('ruc.import_delimiter'), 'queue_name' => (string) config('ruc.import_queue'),
            'last_message' => 'Archivo guardado; esperando al worker.', 'created_by' => $actorId,
        ]);
        if ($dispatch) {
            ProcessRucImportJob::dispatch($import->id)
                ->onConnection('redis')
                ->onQueue((string) config('ruc.import_queue'));
        }

        return $import;
    }
}
