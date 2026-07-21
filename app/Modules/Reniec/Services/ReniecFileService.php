<?php

namespace App\Modules\Reniec\Services;

use App\Modules\Reniec\Enums\ReniecImportStatus;
use App\Modules\Reniec\Models\ReniecImport;
use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Models\RucImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReniecFileService
{
    public function __construct(private readonly ReniecIncomingFileScanner $scanner) {}

    public function available(): array
    {
        return $this->scanner->scan();
    }

    public function register(string $path, ?int $actor = null, ?string $strategy = null): ReniecImport
    {
        if (RucImport::query()->whereIn('status', [RucImportStatus::Pending, RucImportStatus::Queued, RucImportStatus::Validating, RucImportStatus::Processing])->exists()) {
            throw ValidationException::withMessages(['file' => 'Existe una importación RUC activa. Finalízala antes de iniciar RENIEC.']);
        }

        $diskName = (string) config('reniec.import.disk');
        $disk = Storage::disk($diskName);
        $incoming = $this->scanner->storageDirectory($disk).'/';
        $path = $this->scanner->normalizeIncomingPath($path);

        if (! str_starts_with($path, $incoming) || str_contains($path, '..') || ! $disk->exists($path)) {
            throw ValidationException::withMessages(['file' => 'El archivo debe existir dentro del directorio RENIEC de entrada.']);
        }

        $size = $disk->size($path);
        if ($size === 0) {
            throw ValidationException::withMessages(['file' => 'El archivo RENIEC está vacío.']);
        }

        $absolute = $disk->path($path);
        $free = disk_free_space(dirname($absolute));
        $required = $size * 4;
        if ($free !== false && $free < $required) {
            throw ValidationException::withMessages(['file' => 'Espacio insuficiente: se requiere al menos cuatro veces el tamaño del archivo.']);
        }

        $hash = hash_file('sha256', $absolute);
        if (! is_string($hash)) {
            throw ValidationException::withMessages(['file' => 'No se pudo calcular el checksum del archivo.']);
        }

        return ReniecImport::query()->create([
            'uuid' => (string) Str::uuid(),
            'original_filename' => basename($path),
            'stored_filename' => basename($path),
            'disk' => $diskName,
            'source_path' => $path,
            'file_size' => $size,
            'file_hash' => $hash,
            'status' => ReniecImportStatus::Registered,
            'strategy' => $strategy ?: config('reniec.import.strategy'),
            'metadata' => ['free_space_bytes' => $free, 'required_space_bytes' => $required, 'importer_version' => 1],
            'created_by' => $actor,
        ]);
    }

    public function assertUnchanged(ReniecImport $import): string
    {
        $disk = Storage::disk($import->disk);
        if (! $disk->exists($import->source_path) || $disk->size($import->source_path) !== $import->file_size) {
            throw ValidationException::withMessages(['file' => 'El archivo cambió o ya no existe; no puede reanudarse.']);
        }

        $path = $disk->path($import->source_path);
        if (config('reniec.import.validate_checksum') && hash_file('sha256', $path) !== $import->file_hash) {
            throw ValidationException::withMessages(['file' => 'El checksum cambió; la reanudación fue bloqueada.']);
        }

        if ($import->current_byte_offset > 0 && DB::table('reniec_import_staging')->where('import_id', $import->id)->count() !== $import->valid_rows) {
            throw ValidationException::withMessages(['file' => 'La tabla staging no coincide con el checkpoint; la reanudación fue bloqueada.']);
        }

        return $path;
    }
}
