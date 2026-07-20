<?php

namespace App\Livewire\Admin\Settings;

use App\Services\ApiDocumentationSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ApiDocumentation extends Component
{
    public bool $public = false;

    public function mount(ApiDocumentationSettingsService $settings): void
    {
        Gate::authorize('settings.api-documentation.update');
        $this->public = $settings->isPublic();
    }

    public function save(ApiDocumentationSettingsService $settings): void
    {
        Gate::authorize('settings.api-documentation.update');
        $this->validate(['public' => ['boolean']]);
        $settings->save($this->public);
        $this->dispatch('toast', type: 'success', message: 'Visibilidad de documentación actualizada.');
    }

    public function render(): View
    {
        return view('livewire.admin.settings.api-documentation')
            ->layout('layouts.app', ['pageTitle' => 'Documentación API']);
    }
}
