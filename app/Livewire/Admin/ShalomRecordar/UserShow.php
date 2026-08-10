<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ShalomRecordar;

use App\Core\Audit\AuditLogger;
use App\Models\User;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class UserShow extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public User $user;

    public function mount(User $user): void
    {
        Gate::authorize('shalom-recordar.view');
        $this->user = $user;
    }

    public function render(): View
    {
        $installations = ShalomRecordarInstallation::query()
            ->where('user_id', $this->user->id)
            ->withCount('records')
            ->latest('updated_at')
            ->get();

        $records = ShalomRecordarRecord::query()
            ->where('user_id', $this->user->id)
            ->when($this->search !== '', fn ($query) => $query->where(function ($sub): void {
                $sub->where('field', 'like', '%'.$this->search.'%')
                    ->orWhere('value', 'like', '%'.$this->search.'%');
            }))
            ->latest('recorded_at')
            ->paginate(15);

        return view('livewire.admin.shalom-recordar.user-show', compact('installations', 'records'))
            ->layout('layouts.app', ['pageTitle' => 'Shalom Recordar · '.$this->user->name]);
    }

    public function deleteAllSyncs(AuditLogger $audit): void
    {
        Gate::authorize('shalom-recordar.manage');

        $count = ShalomRecordarRecord::query()->where('user_id', $this->user->id)->count();

        DB::transaction(function () use ($audit, $count): void {
            $records = ShalomRecordarRecord::query()->where('user_id', $this->user->id)->get();

            foreach ($records as $record) {
                $audit->log($record, 'shalom_recordar_user_records_deleted', [
                    'user_id' => $this->user->id,
                ], [], ['user_id']);
                $record->delete();
            }

            $audit->log($this->user, 'shalom_recordar_user_syncs_deleted', [
                'user_id' => $this->user->id,
                'records' => $count,
            ], [], ['user_id', 'records']);
        });

        $this->dispatch('toast', type: 'success', message: $count.' sincronizaciones del usuario eliminadas.');
    }

    public function revokeInstallationToken(int $installationId, AuditLogger $audit): void
    {
        Gate::authorize('shalom-recordar.manage');

        $installation = ShalomRecordarInstallation::query()
            ->where('user_id', $this->user->id)
            ->findOrFail($installationId);

        app(\App\Modules\ShalomRecordar\Services\ShalomRecordarSyncService::class)->revokeInstallationToken($installation, $audit);
        $this->dispatch('toast', type: 'success', message: 'Token de la instalación revocado.');
    }
}
