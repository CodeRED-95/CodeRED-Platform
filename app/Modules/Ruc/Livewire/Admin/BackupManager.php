<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Livewire\Admin;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Services\RucBackupService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class BackupManager extends Component
{
    use WithFileUploads, WithPagination;

    public mixed $backup_file = null;
    public bool $loading = false;
    public bool $show_upload = false;

    #[Url]
    public string $status_filter = '';

    #[Url]
    public int $perPage = 10;

    public function mount(): void
    {
        Gate::authorize('ruc.import-history');
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function getBackupsProperty()
    {
        $query = RucBackup::query();

        if ($this->status_filter) {
            $query->where('status', $this->status_filter);
        }

        return $query->latest('created_at')->paginate($this->perPage);
    }

    public function validateUpload(): void
    {
        $this->validate([
            'backup_file' => 'required|file|mimes:gz|max:10485760',
        ]);
    }

    public function uploadBackup(): void
    {
        Gate::authorize('ruc.import');

        try {
            $this->validateUpload();

            $this->loading = true;

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
            $this->resetPage();

            $this->dispatch('toast', type: 'success', message: 'Backup cargado exitosamente. Tamaño: ' . $this->formatBytes($fileSize));

            Log::info('RUC backup uploaded', ['user_id' => auth()->id(), 'file_name' => $fileName, 'size' => $fileSize]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: 'Validación fallida: ' . $e->validator->errors()->first());
        } catch (\Throwable $e) {
            Log::error('RUC backup upload failed', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);
            $this->dispatch('toast', type: 'error', message: 'Error al cargar: ' . $e->getMessage());
        } finally {
            $this->loading = false;
        }
    }

    public function restoreBackup(RucBackup $backup): void
    {
        Gate::authorize('ruc.import');

        if ($backup->status !== 'completed') {
            $this->dispatch('toast', type: 'error', message: 'El backup debe estar en estado Completado');
            return;
        }

        if (!file_exists($backup->storage_path)) {
            $this->dispatch('toast', type: 'error', message: 'Archivo de backup no encontrado');
            return;
        }

        $this->loading = true;

        try {
            $service = new RucBackupService();
            $result = $service->restore($backup, dryRun: false);

            $this->dispatch('toast', type: 'success', message: "✓ Restauración completada: {$result['records_restored']} registros en {$result['duration_seconds']}s");
            $this->resetPage();

            Log::warning('RUC backup restored', ['backup_id' => $backup->id, 'records' => $result['records_restored'], 'user_id' => auth()->id()]);

        } catch (\Throwable $e) {
            Log::error('RUC restore failed', ['backup_id' => $backup->id, 'error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error al restaurar: ' . $e->getMessage());
        } finally {
            $this->loading = false;
        }
    }

    public function deleteBackup(RucBackup $backup): void
    {
        Gate::authorize('ruc.import');

        try {
            if (file_exists($backup->storage_path)) {
                @unlink($backup->storage_path);
            }

            $backup->forceDelete();
            $this->resetPage();

            $this->dispatch('toast', type: 'success', message: 'Backup eliminado correctamente');
            Log::info('RUC backup deleted', ['backup_id' => $backup->id, 'user_id' => auth()->id()]);

        } catch (\Throwable $e) {
            Log::error('RUC backup deletion failed', ['backup_id' => $backup->id, 'error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error al eliminar: ' . $e->getMessage());
        }
    }

    public function download(RucBackup $backup)
    {
        Gate::authorize('ruc.import');

        if (!file_exists($backup->storage_path)) {
            $this->dispatch('toast', type: 'error', message: 'Archivo no encontrado en el servidor');
            return;
        }

        Log::info('RUC backup downloaded', ['backup_id' => $backup->id, 'user_id' => auth()->id()]);

        return response()->download($backup->storage_path, $backup->name);
    }

    public function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function render()
    {
        return view('ruc.admin.backup-manager', [
            'backups' => $this->backups,
        ]);
    }
}
