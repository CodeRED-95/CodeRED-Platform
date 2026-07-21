<?php

namespace App\Livewire\Admin\Reniec;

use App\Modules\Reniec\Enums\ReniecImportStatus;
use App\Modules\Reniec\Jobs\ProcessReniecImportJob;
use App\Modules\Reniec\Models\ReniecImport;
use App\Modules\Reniec\Services\ReniecFileService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Imports extends Component
{
    public string $selectedPath = '';

    public string $strategy = 'insert_ignore';

    public function mount(): void
    {
        Gate::authorize('reniec.manage');
    }

    public function registerAndStart(ReniecFileService $files): void
    {
        Gate::authorize('reniec.manage');
        $this->validate(['selectedPath' => 'required', 'strategy' => 'in:insert_ignore,upsert'], ['selectedPath.required' => 'Selecciona un archivo disponible.']);
        $import = $files->register($this->selectedPath, (int) auth()->id(), $this->strategy);
        $import->update(['status' => ReniecImportStatus::Queued]);
        ProcessReniecImportJob::dispatch($import->id);
        $this->selectedPath = '';
        $this->dispatch('toast', type: 'success', message: 'Importación RENIEC enviada al worker exclusivo.');
    }

    public function pause(int $id): void
    {
        Gate::authorize('reniec.manage');
        ReniecImport::query()->findOrFail($id)->update(['paused_at' => now()]);
    }

    public function resume(int $id, ReniecFileService $files): void
    {
        Gate::authorize('reniec.manage');
        $import = ReniecImport::query()->findOrFail($id);
        $files->assertUnchanged($import);
        $import->update(['status' => ReniecImportStatus::Queued, 'paused_at' => null, 'cancel_requested_at' => null, 'resumed_at' => now()]);
        ProcessReniecImportJob::dispatch($id);
    }

    public function cancel(int $id): void
    {
        Gate::authorize('reniec.manage');
        ReniecImport::query()->findOrFail($id)->update(['status' => ReniecImportStatus::Cancelling, 'cancel_requested_at' => now()]);
    }

    public function render(ReniecFileService $files): View
    {
        return view('livewire.admin.reniec.imports', ['availableFiles' => $files->available(), 'active' => ReniecImport::query()->whereIn('status', array_map(fn ($s) => $s->value, array_filter(ReniecImportStatus::cases(), fn ($s) => $s->active())))->latest()->first(), 'imports' => ReniecImport::query()->with('createdBy')->latest()->paginate(20)])->layout('layouts.app', ['pageTitle' => 'Importaciones RENIEC']);
    }
}
