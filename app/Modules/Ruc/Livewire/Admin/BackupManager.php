<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Livewire\Admin;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Services\RucBackupService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BackupManager extends Component
{
    use WithPagination;

    public bool $show_upload = false;

    public bool $loading = false;

    #[Url]
    public string $status_filter = '';

    #[Url]
    public int $perPage = 10;

    public function mount(): void
    {
        Gate::authorize('ruc.backup.view');
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

    public function restoreBackup(int $backupId): void
    {
        Gate::authorize('ruc.backup.restore');

        $backup = RucBackup::findOrFail($backupId);

        if ($backup->status !== 'completed') {
            $this->dispatch('toast', type: 'error', message: 'El backup debe estar en estado Completado');

            return;
        }

        if (! file_exists($backup->storage_path)) {
            $this->dispatch('toast', type: 'error', message: 'Archivo de backup no encontrado');

            return;
        }

        $this->loading = true;

        try {
            $service = new RucBackupService;
            $result = $service->restore($backup, dryRun: false);

            $this->dispatch('toast', type: 'success', message: "✓ Restauración completada: {$result['records_restored']} registros en {$result['duration_seconds']}s");
            $this->resetPage();

            Log::warning('RUC backup restored', ['backup_id' => $backup->id, 'records' => $result['records_restored'], 'user_id' => auth()->id()]);

        } catch (\Throwable $e) {
            Log::error('RUC restore failed', ['backup_id' => $backup->id, 'error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error al restaurar: '.$e->getMessage());
        } finally {
            $this->loading = false;
        }
    }

    public function deleteBackup(int $backupId): void
    {
        Gate::authorize('ruc.backup.delete');

        $backup = RucBackup::findOrFail($backupId);

        try {
            if (file_exists($backup->storage_path)) {
                @unlink($backup->storage_path);
            }

            $backup->forceDelete();
            $this->resetPage();

            $this->dispatch('toast', type: 'success', message: 'Backup eliminado correctamente');
            Log::info('RUC backup deleted', ['backup_id' => $backup->id, 'user_id' => auth()->id()]);

        } catch (\Throwable $e) {
            Log::error('RUC backup deletion failed', ['backup_id' => $backupId, 'error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Error al eliminar: '.$e->getMessage());
        }
    }

    public function download(int $backupId)
    {
        Gate::authorize('ruc.backup.download');

        $backup = RucBackup::findOrFail($backupId);

        if (! file_exists($backup->storage_path)) {
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

        return round($bytes, 2).' '.$units[$pow];
    }

    public function render()
    {
        return view('ruc.admin.backup-manager', [
            'backups' => $this->backups,
        ]);
    }
}
