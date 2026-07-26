<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Actions\ConfirmAgencyImportRunAction;
use App\Modules\Agencies\Jobs\SyncShalomAgenciesJob;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImportRun;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class ShalomSyncRun extends Component
{
    use WithPagination;

    public AgencyImportRun $importRun;

    public string $action = '';

    public int $perPage = 25;

    public function mount(AgencyImportRun $importRun): void
    {
        Gate::authorize('viewAny', Agency::class);
        $this->importRun = $importRun;
    }

    public function toggleItem(int $itemId): void
    {
        Gate::authorize('import', Agency::class);
        abort_unless($this->importRun->status === 'ready_for_review', 409);
        $item = $this->importRun->items()->findOrFail($itemId);
        abort_if(in_array($item->action, ['conflict', 'invalid', 'unchanged', 'missing'], true) && ! $item->selected, 422);
        $item->update(['selected' => ! $item->selected]);
    }

    public function selectAction(string $action, bool $selected): void
    {
        Gate::authorize('import', Agency::class);
        abort_unless($this->importRun->status === 'ready_for_review', 409);
        abort_unless(in_array($action, ['create', 'update', 'rename'], true), 422);
        $this->importRun->items()->where('action', $action)->update(['selected' => $selected]);
    }

    public function retry(): void
    {
        Gate::authorize('import', Agency::class);

        if (in_array($this->importRun->status, ['pending', 'processing'], true)) {
            $this->dispatch('notify', type: 'warning', message: 'El análisis ya está en cola o en proceso.');

            return;
        }

        abort_unless($this->importRun->status === 'failed', 409);
        abort_unless(filled($this->importRun->chosen_storage_path), 422);

        $this->importRun->forceFill([
            'status' => 'pending',
            'stage' => 'En cola',
            'progress' => 0,
            'started_at' => null,
            'finished_at' => null,
            'error_message' => null,
        ])->save();

        SyncShalomAgenciesJob::dispatch($this->importRun->id, $this->importRun->chosen_storage_path)
            ->onConnection('redis')
            ->onQueue('agency-imports')
            ->afterCommit();

        $this->dispatch('toast', type: 'success', message: 'La sincronización fue enviada nuevamente a la cola agency-imports.');
    }

    public function confirm(ConfirmAgencyImportRunAction $confirm): void
    {
        Gate::authorize('import', Agency::class);
        $result = $confirm->execute($this->importRun->fresh(), auth()->id());
        $this->importRun->refresh();
        $this->dispatch('toast', type: 'success', message: "Importación completada: {$result['created']} nuevas, {$result['updated']} actualizadas y {$result['renamed']} renombradas.");
    }

    public function render()
    {
        $this->importRun->refresh();
        $items = $this->importRun->items()
            ->when($this->action !== '', fn ($query) => $query->where('action', $this->action))
            ->orderByRaw("CASE action WHEN 'conflict' THEN 1 WHEN 'rename' THEN 2 WHEN 'create' THEN 3 WHEN 'update' THEN 4 ELSE 5 END")
            ->paginate($this->perPage);

        return view('livewire.admin.agencies.shalom-sync-run', [
            'items' => $items,
            'counts' => $this->importRun->items()->selectRaw('action, count(*) total')->groupBy('action')->pluck('total', 'action'),
        ])->layout('layouts.app', ['pageTitle' => 'Vista previa Shalom']);
    }
}
