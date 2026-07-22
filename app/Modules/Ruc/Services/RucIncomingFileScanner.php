<?php

namespace App\Modules\Ruc\Services;

use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Models\RucImport;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class RucIncomingFileScanner
{
    /** @return list<array{path: string, name: string, size: int, last_modified: int, status: string, import_id: int|null}> */
    public function scan(): array
    {
        $disk = $this->disk();
        $directory = $this->storageDirectory($disk);

        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $paths = $disk->files($directory);
        $imports = RucImport::query()
            ->whereIn('path', $paths)
            ->latest('id')
            ->get()
            ->unique('path')
            ->keyBy('path');

        return collect($paths)
            ->filter(fn (string $path): bool => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'txt')
            ->map(function (string $path) use ($disk, $imports): array {
                $import = $imports->get($path);
                $imported = $import !== null && in_array($import->status, [RucImportStatus::Completed, RucImportStatus::CompletedWithErrors], true);

                return [
                    'path' => $path,
                    'name' => basename($path),
                    'size' => $disk->size($path),
                    'last_modified' => $disk->lastModified($path),
                    'status' => $imported ? 'importado' : ($import === null ? 'no_registrado' : 'registrado'),
                    'import_id' => $import?->id,
                ];
            })
            ->sortByDesc('last_modified')
            ->values()
            ->all();
    }

    public function diagnostics(): array
    {
        $disk = $this->disk();
        $directory = $this->storageDirectory($disk);
        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }
        $path = $disk->path($directory);

        return [
            'disk' => (string) config('ruc.import.disk'),
            'configured_directory' => trim((string) config('ruc.import.incoming_directory'), '/'),
            'storage_directory' => $directory,
            'physical_path' => $path,
            'exists' => $disk->exists($directory),
            'readable' => is_dir($path) && is_readable($path),
            'free_space_bytes' => disk_free_space($path) ?: 0,
            'txt_count' => count($this->scan()),
        ];
    }

    public function normalizeIncomingPath(string $path): string
    {
        $path = ltrim($path, '/');
        $configured = trim((string) config('ruc.import.incoming_directory'), '/');
        $storage = $this->storageDirectory($this->disk());

        if ($configured !== $storage && str_starts_with($path, $configured.'/')) {
            return $storage.'/'.substr($path, strlen($configured) + 1);
        }

        return $path;
    }

    public function resolveIncomingPath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/') || str_starts_with($path, '\\') || str_contains($path, '..') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            throw ValidationException::withMessages(['incomingFiles' => 'La ruta del archivo no es válida.']);
        }
        $path = $this->normalizeIncomingPath($path);
        $directory = $this->storageDirectory();
        if (! str_starts_with($path, $directory.'/')) {
            throw ValidationException::withMessages(['incomingFiles' => 'El archivo no pertenece al directorio de entrada.']);
        }
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'txt') {
            throw ValidationException::withMessages(['incomingFiles' => 'Solo se permiten archivos TXT.']);
        }
        if (! $this->disk()->exists($path)) {
            throw ValidationException::withMessages(['incomingFiles' => 'El archivo seleccionado ya no existe.']);
        }

        return $path;
    }

    public function storageDirectory(?FilesystemAdapter $disk = null): string
    {
        return $this->resolveDirectory((string) config('ruc.import.incoming_directory'), $disk);
    }

    public function resolveDirectory(string $configured, ?FilesystemAdapter $disk = null): string
    {
        $disk ??= $this->disk();
        $configured = trim($configured, '/');
        $root = rtrim($disk->path(''), DIRECTORY_SEPARATOR);

        return basename($root) === 'private' && str_starts_with($configured, 'private/')
            ? substr($configured, strlen('private/'))
            : $configured;
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('ruc.import.disk'));
    }
}
