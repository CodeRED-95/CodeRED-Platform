<?php

namespace App\Livewire\Admin\Settings;

use App\Modules\Agencies\Services\AgencyBackupSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AgencyBackups extends Component
{
    public int $maximumBackups = 10;

    public int $retentionDays = 30;

    public bool $autoCleanup = false;

    public function mount(AgencyBackupSettingsService $settings): void
    {
        Gate::authorize('settings.agency-backups.update');
        $this->maximumBackups = $settings->maximumBackups();
        $this->retentionDays = $settings->retentionDays();
        $this->autoCleanup = $settings->autoCleanup();
    }

    public function save(AgencyBackupSettingsService $settings): void
    {
        Gate::authorize('settings.agency-backups.update');
        $values = $this->validate([
            'maximumBackups' => ['required', 'integer', 'min:1', 'max:100'],
            'retentionDays' => ['required', 'integer', 'min:1', 'max:3650'],
            'autoCleanup' => ['boolean'],
        ]);
        $settings->save($values['maximumBackups'], $values['retentionDays'], $values['autoCleanup']);
        $this->dispatch('toast', type: 'success', message: 'Política de copias actualizada.');
    }

    public function render(): View
    {
        return view('livewire.admin.settings.agency-backups')->layout('layouts.app', ['pageTitle' => 'Ajustes de copias']);
    }
}
