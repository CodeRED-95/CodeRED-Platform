<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Http\Controllers;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Requests\UploadBackupRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class BackupUploadController
{
    /**
     * Subir archivo de backup
     */
    public function upload(UploadBackupRequest $request)
    {
        Gate::authorize('upload', RucBackup::class);

        $file = $request->file('backup_file');
        $backupDir = storage_path('app/backups/ruc');

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $fileName = 'ruc_backup_uploaded_' . now()->format('Y-m-d-His') . '.sql.gz';
        $filePath = $backupDir . '/' . $fileName;

        try {
            $file->move($backupDir, $fileName);

            // Crear registro en BD
            $recordCount = 0;
            $fileSize = filesize($filePath);
            $checksum = hash_file('sha256', $filePath);

            $backup = RucBackup::create([
                'name' => $fileName,
                'backup_type' => 'uploaded',
                'storage_type' => 'local',
                'status' => 'completed',
                'total_records' => $recordCount,
                'file_size_bytes' => $fileSize,
                'storage_path' => $filePath,
                'checksum_sha256' => $checksum,
                'created_by' => Auth::id(),
            ]);

            Log::info('Backup uploaded successfully', [
                'backup_id' => $backup->id,
                'file_size' => $this->formatBytes($fileSize),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Archivo cargado exitosamente',
                'backup' => $backup,
            ]);

        } catch (\Throwable $e) {
            Log::error('Backup upload failed', ['error' => $e->getMessage()]);

            if (file_exists($filePath)) {
                @unlink($filePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar el archivo: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Formatear bytes a formato legible
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $bytes;

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }
}
