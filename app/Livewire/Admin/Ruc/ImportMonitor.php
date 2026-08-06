<?php

namespace App\Livewire\Admin\Ruc;

use App\Modules\Ruc\Models\RucImport;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ImportMonitor extends Component
{
    public int $importId;
    public ?RucImport $import = null;
    public array $progressData = [];
    public bool $autoRefresh = true;

    protected array $listeners = [
        'import-progress' => 'onProgressUpdate',
    ];

    public function mount(int $importId)
    {
        $this->importId = $importId;
        $this->import = RucImport::findOrFail($importId);
        $this->authorize('view', $this->import);
        $this->refreshData();
    }

    #[On('import-progress')]
    public function onProgressUpdate($data)
    {
        if ($data['import_id'] === $this->importId) {
            $this->progressData = $data;
            $this->import->refresh();
        }
    }

    public function refreshData(): void
    {
        if ($this->import) {
            $this->import->refresh();
            $this->progressData = [
                'progress' => $this->import->getProgressPercentage(),
                'lines_processed' => $this->import->processed_lines,
                'total_lines' => $this->import->total_lines,
                'records_inserted' => $this->import->inserted_records,
                'errors' => $this->import->invalid_rows,
                'duplicates' => $this->import->duplicate_records,
                'speed' => $this->import->lines_per_second,
                'eta' => $this->import->estimated_time_left,
                'memory' => $this->import->memory_peak_mb,
                'status' => $this->import->status,
            ];
        }
    }

    public function pause()
    {
        if ($this->import->canCancel()) {
            $this->import->update(['status' => 'paused', 'paused_at' => now()]);
            $this->dispatch('notify', type: 'success', message: 'Importación pausada');
        }
    }

    public function resume()
    {
        if ($this->import->canResume()) {
            \App\Modules\Ruc\Jobs\ProcessRucImportJobV3::dispatch($this->import->id)
                ->onQueue(config('ruc.import.queue', 'ruc-imports'));
            $this->import->update(['status' => 'processing']);
            $this->dispatch('notify', type: 'success', message: 'Importación reanudada');
        }
    }

    public function cancel()
    {
        if ($this->import->canCancel()) {
            $this->import->requestCancellation(auth()->user());
            $this->dispatch('notify', type: 'info', message: 'Cancelación solicitada');
        }
    }

    #[Computed]
    public function isProcessing(): bool
    {
        return $this->import && $this->import->status === 'processing';
    }

    #[Computed]
    public function isCompleted(): bool
    {
        return $this->import && in_array($this->import->status, ['completed', 'completed_with_errors']);
    }

    public function render()
    {
        return view('livewire.admin.ruc.import-monitor', [
            'import' => $this->import,
        ]);
    }
}
