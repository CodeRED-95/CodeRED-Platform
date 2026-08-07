<?php

namespace App\Modules\Ruc\Services;

use App\Models\User;
use App\Modules\Ruc\Enums\RucImportStatusV3;
use App\Modules\Ruc\Jobs\ProcessRucImportJobV3;
use App\Modules\Ruc\Models\RucImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RucImportOrchestrator
{
    public function __construct(
        private readonly RucFileStreamReader $fileReader
    ) {}

    /**
     * Inicia una importación desde un archivo cargado
     */
    public function initiateFromUpload(
        UploadedFile $file,
        User $user,
        array $options = []
    ): RucImport {
        // Validar archivo
        $this->validateUpload($file);

        // Guardar archivo
        $storedPath = $this->storeFile($file);

        // Calcular hash
        $fileHash = $this->calculateHash($storedPath);

        // Crear registro de importación
        $import = $this->createImportRecord(
            $file,
            $storedPath,
            $fileHash,
            $user,
            $options
        );

        // Registrar evento de inicio
        $import->recordEvent('import.started', [
            'file_size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
            'initiated_by' => $user->id,
        ], $user);

        // Despachar job
        $this->dispatchJob($import);

        return $import;
    }

    /**
     * Valida que el archivo sea válido
     */
    private function validateUpload(UploadedFile $file): void
    {
        // Validar tipo MIME
        $validMimes = ['text/plain', 'application/octet-stream'];
        if (! in_array($file->getMimeType(), $validMimes)) {
            throw new RuntimeException('El archivo debe ser de tipo texto (.txt)');
        }

        // Validar extensión
        if ($file->getClientOriginalExtension() !== 'txt') {
            throw new RuntimeException('El archivo debe tener extensión .txt');
        }

        // Validar tamaño (RUC_IMPORT_MAX_SIZE_MB; config('ruc.import.max_file_size')
        // no existe, así que antes esto ignoraba el límite configurado)
        $maxSize = max(1, (int) config('ruc.import.max_size_mb', 30000)) * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            throw new RuntimeException(
                'El archivo excede el tamaño máximo de '.
                $this->formatBytes($maxSize)
            );
        }

        // Validar espacio en disco
        $disk = Storage::disk(config('ruc.import.disk', 'local'));
        $available = disk_free_space($disk->path(''));
        if ($available < $file->getSize()) {
            throw new RuntimeException('Espacio en disco insuficiente');
        }
    }

    /**
     * Guarda el archivo de forma segura
     */
    private function storeFile(UploadedFile $file): string
    {
        $disk = Storage::disk(config('ruc.import.disk', 'local'));
        $directory = config('ruc.import.directories.incoming', 'private/ruc/incoming');

        // Generar nombre seguro
        $filename = Str::uuid().'-'.preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());

        // Guardar
        $path = $disk->putFileAs($directory, $file, $filename, 'private');
        if (! $path) {
            throw new RuntimeException('No se pudo guardar el archivo');
        }

        return $path;
    }

    /**
     * Calcula el hash SHA-256 del archivo
     */
    private function calculateHash(string $path): string
    {
        $disk = Storage::disk(config('ruc.import.disk', 'local'));
        $fullPath = $disk->path($path);

        $hash = hash_file('sha256', $fullPath);
        if ($hash === false) {
            throw new RuntimeException('No se pudo calcular hash del archivo');
        }

        return $hash;
    }

    /**
     * Crea el registro de importación en la BD
     */
    private function createImportRecord(
        UploadedFile $file,
        string $path,
        string $hash,
        User $user,
        array $options
    ): RucImport {
        $disk = Storage::disk(config('ruc.import.disk', 'local'));

        return RucImport::create([
            'uuid' => Str::uuid(),
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => basename($path),
            'disk' => $disk->getName(),
            'path' => $path,
            'file_size' => $file->getSize(),
            'file_hash' => $hash,
            'status' => RucImportStatusV3::Pending->value,
            'encoding' => $options['encoding'] ?? 'UTF-8',
            'delimiter' => $options['delimiter'] ?? '|',
            'created_by' => $user->id,
            'merge_strategy' => $options['merge_strategy'] ?? 'insert',
            'skip_duplicates' => $options['skip_duplicates'] ?? true,
            'skip_unknown_ubigeo' => $options['skip_unknown_ubigeo'] ?? false,
            'max_errors_allowed' => $options['max_errors_allowed'] ?? null,
        ]);
    }

    /**
     * Despacha el job de procesamiento
     */
    private function dispatchJob(RucImport $import): void
    {
        $queue = config('ruc.import.queue', 'ruc-imports');
        $delay = config('ruc.import.job_delay', 0);

        ProcessRucImportJobV3::dispatch($import->id)
            ->onQueue($queue)
            ->delay($delay);
    }

    /**
     * Formatea bytes a formato legible
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1 << (10 * $pow);

        return round($bytes, 2).' '.$units[$pow];
    }
}
