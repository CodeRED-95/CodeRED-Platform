<?php

namespace App\Livewire\Admin\Ruc;

use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Services\RucImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class Imports extends Component
{
    use WithFileUploads;

    public $file;

    public bool $force = false;

    public function mount(): void
    {
        Gate::authorize('ruc.import-history');
    }

    public function start(RucImportService $service): void
    {
        Gate::authorize('ruc.import');
        $this->validate(['file' => ['required', 'file', 'mimes:txt', 'max:'.(max(1, (int) config('ruc.import_max_size_mb')) * 1024)]]);
        $import = $service->fromUpload($this->file, (int) auth()->id(), $this->force);
        $this->reset(['file', 'force']);
        $this->dispatch('toast', type: 'success', message: 'Importación encolada: '.$import->uuid);
    }

    public function cancel(int $id): void
    {
        Gate::authorize('ruc.cancel-import');
        $import = RucImport::query()->findOrFail($id);
        abort_unless($import->status->active(), 422);
        $import->update(['status' => RucImportStatus::Cancelled, 'finished_at' => now()]);
    }

    public function deleteFile(int $id): void
    {
        Gate::authorize('ruc.delete-import-file');
        $import = RucImport::query()->findOrFail($id);
        abort_if($import->status->active(), 422);
        Storage::disk($import->disk)->delete($import->path);
        $import->update(['path' => 'deleted']);
        $this->dispatch('toast', type: 'success', message: 'Archivo fuente eliminado; el historial fue conservado.');
    }

    public function render(): View
    {
        $active = RucImport::query()->whereIn('status', [RucImportStatus::Pending, RucImportStatus::Queued, RucImportStatus::Validating, RucImportStatus::Processing])->latest()->first();
        $elapsed = $active?->started_at?->diffInSeconds(now()) ?? 0;
        $speed = $elapsed > 0 ? round($active->processed_rows / $elapsed, 1) : 0;
        $remaining = $speed > 0 && $active?->total_rows > 0 ? max(0, (int) round(($active->total_rows - $active->processed_rows) / $speed)) : null;

        return view('livewire.admin.ruc.imports', ['activeImport' => $active, 'speed' => $speed, 'remainingSeconds' => $remaining, 'imports' => RucImport::query()->with('createdBy')->latest()->paginate(20)])
            ->layout('layouts.app', ['pageTitle' => 'Importaciones RUC']);
    }
}
