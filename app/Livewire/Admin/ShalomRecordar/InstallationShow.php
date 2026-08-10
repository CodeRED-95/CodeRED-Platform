<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ShalomRecordar;

use App\Core\Audit\AuditLogger;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class InstallationShow extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public ShalomRecordarInstallation $installation;

    public function mount(ShalomRecordarInstallation $installation): void
    {
        Gate::authorize('shalom-recordar.view');
        $this->installation = $installation->load('user');
    }

    public function render(): View
    {
        $batches = ShalomRecordarRecord::query()
            ->selectRaw('coalesce(sync_batch_id, sync_cursor, recorded_at::text) as batch_id')
            ->selectRaw('count(*) as records_count')
            ->where('installation_id', $this->installation->id)
            ->groupByRaw('coalesce(sync_batch_id, sync_cursor, recorded_at::text)')
            ->orderByDesc('batch_id')
            ->get();

        $records = ShalomRecordarRecord::query()
            ->where('installation_id', $this->installation->id)
            ->when($this->search !== '', fn ($query) => $query->where(function ($sub): void {
                $sub->where('field', 'like', '%'.$this->search.'%')
                    ->orWhere('value', 'like', '%'.$this->search.'%');
            }))
            ->latest('recorded_at')
            ->paginate(20);

        return view('livewire.admin.shalom-recordar.installation-show', compact('records', 'batches'))
            ->layout('layouts.app', ['pageTitle' => 'Instalación Shalom Recordar']);
    }

    public function deleteSyncBatch(string $batchId, AuditLogger $audit): void
    {
        Gate::authorize('shalom-recordar.manage');

        $deleted = app(\App\Modules\ShalomRecordar\Services\ShalomRecordarSyncService::class)->deleteSyncBatch($this->installation, $batchId, $audit);
        $this->dispatch('toast', type: 'success', message: $deleted.' registros eliminados del lote.');
    }

    public function revokeInstallationToken(AuditLogger $audit): void
    {
        Gate::authorize('shalom-recordar.manage');

        app(\App\Modules\ShalomRecordar\Services\ShalomRecordarSyncService::class)->revokeInstallationToken($this->installation, $audit);
        $this->dispatch('toast', type: 'success', message: 'Token de la instalación revocado.');
    }

    public function deleteInstallationSyncs(AuditLogger $audit): void
    {
        Gate::authorize('shalom-recordar.manage');

        $count = app(\App\Modules\ShalomRecordar\Services\ShalomRecordarSyncService::class)->deleteInstallationSyncs($this->installation, $audit);
        $this->dispatch('toast', type: 'success', message: $count.' registros eliminados de la instalación.');
    }

    public function deleteInstallation(AuditLogger $audit): void
    {
        Gate::authorize('shalom-recordar.manage');

        app(\App\Modules\ShalomRecordar\Services\ShalomRecordarSyncService::class)->deleteInstallation($this->installation, $audit);
        $this->dispatch('toast', type: 'success', message: 'Instalación eliminada.');
        $this->redirectRoute('admin.shalom-recordar.users.show', ['user' => $this->installation->user_id]);
    }
}
