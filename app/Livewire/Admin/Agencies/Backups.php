<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Models\AgencyBackup;
use App\Modules\Agencies\Services\AgencyBackupService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Backups extends Component
{
    public ?string $integrityResult = null;

    public function mount(): void
    {
        Gate::authorize('agencies.backup.view');
    }

    public function create(AgencyBackupService $service): void
    {
        Gate::authorize('agencies.backup.create');
        $backup = $service->create(auth()->id());
        $this->dispatch('toast', type: 'success', message: 'Copia creada: '.$backup->filename);
    }

    public function verifyIntegrity(int $backupId, AgencyBackupService $service): void
    {
        Gate::authorize('agencies.backup.view');
        $result = $service->verify(AgencyBackup::query()->findOrFail($backupId));
        $this->integrityResult = match ($result) {
            'integrity_ok' => 'Íntegro', 'altered' => 'Alterado', default => 'Archivo no encontrado',
        };
    }

    public function delete(int $backupId, AgencyBackupService $service): void
    {
        Gate::authorize('agencies.backup.delete');
        $service->delete(AgencyBackup::query()->findOrFail($backupId));
        $this->dispatch('toast', type: 'success', message: 'Copia eliminada.');
    }

    public function render(): View
    {
        return view('livewire.admin.agencies.backups', [
            'backups' => AgencyBackup::query()->with('createdBy')->latest()->paginate(20),
        ])->layout('layouts.app', ['pageTitle' => 'Copias de seguridad de agencias']);
    }
}
