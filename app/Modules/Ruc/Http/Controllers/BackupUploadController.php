<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Http\Controllers;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Services\RucBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BackupUploadController
{
    // max:10485760 más abajo está en KB (regla `max` de Laravel) => 10 GB reales.
    private const MAX_UPLOAD_KB = 10485760;

    public function create(Request $request)
    {
        Gate::authorize('ruc.backup.create');

        try {
            $service = new RucBackupService;
            $backup = $service->backup('full', auth()->user());

            Log::info('RUC backup created', [
                'user_id' => auth()->id(),
                'backup_id' => $backup->id,
                'file_name' => $backup->name,
                'size' => $backup->file_size_bytes,
            ]);

            return response()->json([
                'success' => true,
                'message' => '✓ Backup creado exitosamente. Registros: '.number_format($backup->total_records ?? 0).', Tamaño: '.$this->formatBytes($backup->file_size_bytes),
                'backup_id' => $backup->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('RUC backup creation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear backup: '.$e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        Gate::authorize('ruc.backup.create');

        try {
            $validated = $request->validate([
                'backup_file' => 'required|file|mimes:gz|max:'.self::MAX_UPLOAD_KB,
            ], [
                'backup_file.required' => 'Debe seleccionar un archivo',
                'backup_file.file' => 'Debe ser un archivo válido',
                'backup_file.mimes' => 'El archivo debe tener extensión .gz',
                'backup_file.max' => 'El archivo no puede exceder '.round(self::MAX_UPLOAD_KB / 1024 / 1024).' GB',
            ]);

            $file = $validated['backup_file'];
            $fileName = 'ruc_backup_uploaded_'.now()->format('Y-m-d-His').'_'.bin2hex(random_bytes(4)).'.sql.gz';
            $path = $file->storeAs('backups/ruc', $fileName);

            $filePath = storage_path('app/'.$path);
            if (! file_exists($filePath)) {
                @unlink($filePath);
                throw new \Exception('El archivo no se almacenó correctamente en el servidor');
            }

            $fileSize = filesize($filePath);
            if ($fileSize === 0) {
                @unlink($filePath);
                throw new \Exception('El archivo está vacío');
            }

            // Rechazar cualquier archivo que no sea un dump real de pg_dump
            // en formato custom: evita que un .gz arbitrario quede aceptado
            // como backup y termine usándose (y truncando la tabla) en un
            // restore posterior.
            try {
                (new RucBackupService)->validateDumpFile($filePath);
            } catch (\Throwable $e) {
                @unlink($filePath);
                throw new \Exception('El archivo no es un dump válido de PostgreSQL: '.$e->getMessage());
            }

            $checksum = hash_file('sha256', $filePath);

            RucBackup::create([
                'name' => $fileName,
                'backup_type' => 'uploaded',
                'storage_type' => 'local',
                'status' => 'completed',
                'file_size_bytes' => $fileSize,
                'storage_path' => $filePath,
                'checksum_sha256' => $checksum,
                'created_by' => auth()->id(),
            ]);

            Log::info('RUC backup uploaded', [
                'user_id' => auth()->id(),
                'file_name' => $fileName,
                'size' => $fileSize,
            ]);

            return response()->json([
                'success' => true,
                'message' => '✓ Backup cargado exitosamente. Tamaño: '.$this->formatBytes($fileSize),
            ]);

        } catch (ValidationException $e) {
            Log::warning('RUC backup validation failed', [
                'user_id' => auth()->id(),
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->errors()['backup_file'][0] ?? 'Validación fallida',
            ], 422);

        } catch (\Throwable $e) {
            Log::error('RUC backup upload failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar: '.$e->getMessage(),
            ], 500);
        }
    }

    private function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }
}
