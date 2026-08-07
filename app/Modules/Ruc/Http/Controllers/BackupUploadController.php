<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Http\Controllers;

use App\Modules\Ruc\Models\RucBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BackupUploadController
{
    public function store(Request $request)
    {
        Gate::authorize('ruc.import');

        try {
            $validated = $request->validate([
                'backup_file' => 'required|file|mimes:gz|max:10485760',
            ]);

            $file = $validated['backup_file'];
            $fileName = 'ruc_backup_uploaded_' . now()->format('Y-m-d-His') . '.sql.gz';
            $path = $file->storeAs('backups/ruc', $fileName);

            $filePath = storage_path('app/' . $path);
            if (!file_exists($filePath)) {
                throw new \Exception('Archivo no fue almacenado correctamente');
            }

            $fileSize = filesize($filePath);
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
                'message' => 'Backup cargado exitosamente. Tamaño: ' . $this->formatBytes($fileSize),
            ]);

        } catch (ValidationException $e) {
            Log::warning('RUC backup validation failed', [
                'user_id' => auth()->id(),
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validación fallida: ' . $e->validator->errors()->first(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('RUC backup upload failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar: ' . $e->getMessage(),
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

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
