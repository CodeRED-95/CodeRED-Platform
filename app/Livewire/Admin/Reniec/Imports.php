<?php

namespace App\Livewire\Admin\Reniec;

use App\Modules\Reniec\Enums\ReniecImportStatus;
use App\Modules\Reniec\Jobs\ProcessReniecImportJob;
use App\Modules\Reniec\Models\ReniecImport;
use App\Modules\Reniec\Services\ReniecFileService;
use App\Modules\Reniec\Services\ReniecIncomingFileScanner;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Imports extends Component
{
    public string $selectedPath = '';

    public string $strategy = 'insert_ignore';

    public array $availableFiles = [];

    public array $diagnostics = [];

    public ?string $validationMessage = null;

    public function mount(ReniecIncomingFileScanner $scanner): void
    {
        Gate::authorize('reniec.manage');
        $this->refreshFiles($scanner);
    }

    public function scanFiles(ReniecIncomingFileScanner $scanner): void
    {
        Gate::authorize('reniec.manage');
        $this->refreshFiles($scanner);
        $this->dispatch('toast', type: 'success', message: count($this->availableFiles).' archivos TXT detectados.');
    }

    public function validateFile(string $path, ReniecIncomingFileScanner $scanner): void
    {
        Gate::authorize('reniec.manage');
        $file = collect($scanner->scan())->firstWhere('path', $path);
        abort_if($file === null, 422, 'El archivo ya no está disponible.');
        $this->validationMessage = 'Archivo válido para registro: '.$file['name'].' ('.number_format($file['size']).' bytes).';
    }

    public function registerFile(string $path, ReniecFileService $files, ReniecIncomingFileScanner $scanner): void
    {
        Gate::authorize('reniec.manage');
        $files->register($path, (int) auth()->id(), $this->strategy);
        $this->refreshFiles($scanner);
        $this->dispatch('toast', type: 'success', message: 'Archivo RENIEC registrado.');
    }

    public function startImport(int $id): void
    {
        Gate::authorize('reniec.manage');
        $import = ReniecImport::query()->findOrFail($id);
        abort_unless($import->status === ReniecImportStatus::Registered, 422);
        $import->update(['status' => ReniecImportStatus::Queued]);
        ProcessReniecImportJob::dispatch($import->id);
        $this->dispatch('toast', type: 'success', message: 'Importación RENIEC enviada al worker exclusivo.');
    }

    public function registerAndStart(ReniecFileService $files, ReniecIncomingFileScanner $scanner): void
    {
        Gate::authorize('reniec.manage');
        $this->validate(['selectedPath' => 'required', 'strategy' => 'in:insert_ignore,upsert'], ['selectedPath.required' => 'Selecciona un archivo disponible.']);
        $import = $files->register($this->selectedPath, (int) auth()->id(), $this->strategy);
        $import->update(['status' => ReniecImportStatus::Queued]);
        ProcessReniecImportJob::dispatch($import->id);
        $this->selectedPath = '';
        $this->refreshFiles($scanner);
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

    public function render(): View
    {
        return view('livewire.admin.reniec.imports', [
            'active' => ReniecImport::query()->whereIn('status', array_map(fn ($status) => $status->value, array_filter(ReniecImportStatus::cases(), fn ($status) => $status->active())))->latest()->first(),
            'imports' => ReniecImport::query()->with('createdBy')->latest()->paginate(20),
        ])->layout('layouts.app', ['pageTitle' => 'Importaciones RENIEC']);
    }

    private function refreshFiles(ReniecIncomingFileScanner $scanner): void
    {
        $this->availableFiles = $scanner->scan();
        $this->diagnostics = $scanner->diagnostics();
    }
}
