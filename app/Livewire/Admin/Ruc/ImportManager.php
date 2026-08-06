<?php

namespace App\Livewire\Admin\Ruc;

use App\Models\User;
use App\Modules\Ruc\Enums\RucImportStatusV3;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Services\RucImportOrchestrator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportManager extends Component
{
    use WithFileUploads;

    public ?RucImport $selectedImport = null;
    public ?string $mergeStrategy = 'insert';
    public bool $skipDuplicates = true;
    public bool $skipUnknownUbigeo = false;
    public bool $uploading = false;
    public string $uploadProgress = '0%';
    public ?string $uploadError = null;

    protected array $listeners = [
        'ruc:import:progress' => 'onImportProgress',
    ];

    public function mount()
    {
        $this->authorize('create', RucImport::class);
    }

    #[Computed]
    public function recentImports(): Collection
    {
        return RucImport::query()
            ->with('createdBy')
            ->latest()
            ->limit(10)
            ->get();
    }

    public function uploadFile($file, RucImportOrchestrator $orchestrator)
    {
        try {
            $this->uploading = true;
            $this->uploadError = null;

            // Iniciar importación
            $this->selectedImport = $orchestrator->initiateFromUpload(
                $file,
                auth()->user(),
                [
                    'merge_strategy' => $this->mergeStrategy,
                    'skip_duplicates' => $this->skipDuplicates,
                    'skip_unknown_ubigeo' => $this->skipUnknownUbigeo,
                ]
            );

            $this->uploading = false;
            $this->dispatch('import-started', importId: $this->selectedImport->id);
        } catch (\Exception $e) {
            $this->uploadError = $e->getMessage();
            $this->uploading = false;
        }
    }

    #[On('ruc:import:progress')]
    public function onImportProgress($data)
    {
        if ($this->selectedImport && $data['import_id'] === $this->selectedImport->id) {
            $this->selectedImport->refresh();
        }
    }

    public function cancelImport()
    {
        if (!$this->selectedImport) {
            return;
        }

        $this->selectedImport->requestCancellation(auth()->user(), 'Cancelado por usuario');
        $this->dispatch('import-cancelled', importId: $this->selectedImport->id);
        $this->selectedImport = null;
    }

    public function render()
    {
        return view('livewire.admin.ruc.import-manager', [
            'imports' => $this->recentImports,
        ]);
    }
}
