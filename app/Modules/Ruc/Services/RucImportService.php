<?php

namespace App\Modules\Ruc\Services;

use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Jobs\PrepareRucImportJob;
use App\Modules\Ruc\Jobs\ProcessRucImportJob;
use App\Modules\Ruc\Models\RucImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RucImportService
{
    public function __construct(private readonly RucIncomingFileScanner $scanner, private readonly RucIncomingFileValidator $validator, private readonly RucFileHasher $hasher) {}

    public function registerServerFile(string $path, ?int $actorId = null, bool $force = false): RucImport
    {
        $diskName = (string) config('ruc.import.disk');
        $path = $this->scanner->resolveIncomingPath($path);
        $result = $this->validator->validate($path);
        if (! $result['valid']) {
            throw ValidationException::withMessages(['incomingFiles' => $result['message']]);
        }
        $size = Storage::disk($diskName)->size($path);
        if ($size > max(1, (int) config('ruc.import.max_size_mb')) * 1024 * 1024) {
            throw ValidationException::withMessages(['incomingFiles' => 'El archivo supera el tamaño máximo configurado.']);
        }
        if ($size > max(1, (int) config('ruc.import.sync_hash_max_mb')) * 1024 * 1024) {
            return $this->registerForPreparation($diskName, $path, $size, $result, $actorId);
        }

        return $this->createFromStoredFile($diskName, $path, basename($path), $actorId, $force, false);
    }

    public function startRegistered(RucImport $import): void
    {
        if ($import->status !== RucImportStatus::Registered) {
            throw ValidationException::withMessages(['file' => 'La importación no está lista para iniciarse.']);
        }
        $this->assertNoActiveImport();
        $import->update(['status' => RucImportStatus::Queued, 'last_message' => 'Archivo registrado; esperando al worker.']);
        ProcessRucImportJob::dispatch($import->id)->onConnection('redis')->onQueue((string) config('ruc.import.queue'));
    }

    public function fromUpload(UploadedFile $file, int $actorId, bool $force = false): RucImport
    {
        return $this->fromPath($file->getRealPath(), $actorId, $force, true, $file->getClientOriginalName());
    }

    public function fromPath(string $source, ?int $actorId = null, bool $force = false, bool $dispatch = true, ?string $originalName = null): RucImport
    {
        if (! is_file($source) || ! is_readable($source)) {
            throw ValidationException::withMessages(['file' => 'El archivo no existe o no es legible.']);
        }
        $diskName = (string) config('ruc.import.disk');
        $disk = Storage::disk($diskName);
        $directory = $this->scanner->resolveDirectory((string) config('ruc.import.working_directory'), $disk);
        $disk->makeDirectory($directory);
        $path = $directory.'/'.Str::uuid().'.txt';
        $stream = fopen($source, 'rb');
        if (! is_resource($stream) || ! $disk->writeStream($path, $stream)) {
            throw ValidationException::withMessages(['file' => 'No se pudo guardar el archivo en el almacenamiento privado.']);
        }
        fclose($stream);
        $import = $this->createFromStoredFile($diskName, $path, $originalName ?? basename($source), $actorId, $force, false);
        if ($dispatch) {
            $this->startRegistered($import);
        }

        return $import->refresh();
    }

    private function createFromStoredFile(string $diskName, string $path, string $originalName, ?int $actorId, bool $force, bool $dispatch): RucImport
    {
        $lock = Cache::lock('ruc-import-submit', 15);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['file' => 'Otra solicitud RUC está siendo registrada.']);
        }
        try {
            $disk = Storage::disk($diskName);
            $size = $disk->size($path);
            if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'txt' || $size < 1) {
                throw ValidationException::withMessages(['file' => 'Selecciona un archivo TXT no vacío.']);
            }
            if ($size > max(1, (int) config('ruc.import.max_size_mb')) * 1024 * 1024) {
                throw ValidationException::withMessages(['file' => 'El archivo supera el tamaño máximo configurado.']);
            }
            $hash = $this->hasher->sha256($diskName, $path);
            if (! $force && RucImport::query()->where('file_hash', $hash)->exists()) {
                throw ValidationException::withMessages(['file' => 'Este archivo ya fue registrado anteriormente.']);
            }
            $uuid = (string) Str::uuid();
            $import = RucImport::query()->create([
                'uuid' => $uuid,
                'original_filename' => basename($originalName),
                'stored_filename' => basename($path),
                'disk' => $diskName,
                'path' => $path,
                'file_size' => $size,
                'file_hash' => $hash,
                'status' => RucImportStatus::Registered,
                'encoding' => (string) config('ruc.import.encoding'),
                'delimiter' => (string) config('ruc.import.delimiter'),
                'queue_name' => (string) config('ruc.import.queue'),
                'last_message' => 'Archivo detectado y registrado; listo para iniciar.',
                'created_by' => $actorId,
            ]);
            Log::info('Archivo RUC registrado', ['import_id' => $import->id, 'disk' => $diskName, 'path' => $path, 'size' => $size]);
            if ($dispatch) {
                $this->startRegistered($import);
            }

            return $import;
        } finally {
            $lock->release();
        }
    }

    private function registerForPreparation(string $diskName, string $path, int $size, array $validation, ?int $actorId): RucImport
    {
        if (RucImport::query()->where('path', $path)->exists()) {
            throw ValidationException::withMessages(['incomingFiles' => 'Este archivo ya fue registrado anteriormente.']);
        }
        $uuid = (string) Str::uuid();
        $import = RucImport::query()->create([
            'uuid' => $uuid,
            'original_filename' => basename($path),
            'stored_filename' => basename($path),
            'disk' => $diskName,
            'path' => $path,
            'file_size' => $size,
            'file_hash' => hash('sha256', 'preparing:'.$uuid),
            'status' => RucImportStatus::Pending,
            'encoding' => $validation['encoding'],
            'delimiter' => $validation['delimiter'],
            'queue_name' => (string) config('ruc.import.queue'),
            'last_message' => 'Preparando: calculando hash SHA-256 en segundo plano.',
            'created_by' => $actorId,
        ]);
        PrepareRucImportJob::dispatch($import->id)->onConnection('redis')->onQueue((string) config('ruc.import.queue'));
        Log::info('Archivo RUC enviado a preparación', ['import_id' => $import->id, 'path' => $path, 'size' => $size]);

        return $import;
    }

    private function assertNoActiveImport(): void
    {
        if (RucImport::query()->whereIn('status', [RucImportStatus::Pending, RucImportStatus::Queued, RucImportStatus::Validating, RucImportStatus::Processing])->exists()) {
            throw ValidationException::withMessages(['file' => 'Ya existe una importación RUC activa.']);
        }
    }
}
