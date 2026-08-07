<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Livewire\Admin;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Services\RucBackupService;
use Livewire\Component;
use Livewire\WithFileUploads;

class BackupManager extends Component
{
    use WithFileUploads;

    public array $backups = [];
    public mixed $backup_file = null;
    public int $page = 1;
    public string $status_filter = 'completed';
    public bool $loading = false;
    public bool $show_upload = false;

    public function mount()
    {
        $this->loadBackups();
    }

    public function loadBackups()
    {
        $query = RucBackup::query();

        if ($this->status_filter) {
            $query->where('status', $this->status_filter);
        }

        $this->backups = $query->latest('created_at')
            ->paginate(10, page: $this->page)
            ->toArray();
    }

    public function download(int $backupId)
    {
        $backup = RucBackup::findOrFail($backupId);

        if (!file_exists($backup->storage_path)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Archivo no encontrado',
            ]);

            return;
        }

        return response()->download($backup->storage_path, $backup->name);
    }

    public function uploadBackup()
    {
        if (!$this->backup_file) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Selecciona un archivo',
            ]);

            return;
        }

        $this->loading = true;

        try {
            $backupDir = storage_path('app/backups/ruc');
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $fileName = 'ruc_backup_uploaded_' . now()->format('Y-m-d-His') . '.sql.gz';
            $path = $this->backup_file->storeAs('backups/ruc', $fileName);

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

            $this->backup_file = null;
            $this->show_upload = false;
            $this->loadBackups();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Backup cargado exitosamente',
            ]);

        } catch (\Throwable $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        } finally {
            $this->loading = false;
        }
    }

    public function restoreBackup(RucBackup $backup)
    {
        if ($backup->status !== 'completed') {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'El backup debe estar completado',
            ]);

            return;
        }

        if (!file_exists($backup->storage_path)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Archivo no encontrado',
            ]);

            return;
        }

        $this->loading = true;

        try {
            $service = new RucBackupService();
            $result = $service->restore($backup, dryRun: false);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Restauración completada: {$result['records_restored']} registros en {$result['duration_seconds']}s",
            ]);

        } catch (\Throwable $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        } finally {
            $this->loading = false;
        }
    }

    public function deleteBackup(RucBackup $backup)
    {
        try {
            if (file_exists($backup->storage_path)) {
                @unlink($backup->storage_path);
            }

            $backup->update(['status' => 'deleted']);
            $this->loadBackups();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Backup eliminado',
            ]);

        } catch (\Throwable $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    public function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $bytes;

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    public function render()
    {
        return view('ruc.admin.backup-manager');
    }
}
