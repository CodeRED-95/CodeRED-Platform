<?php

namespace App\Livewire\Admin;

use App\Services\ApiDocumentationSettingsService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ApiDocumentation extends Component
{
    public function mount(ApiDocumentationSettingsService $settings): void
    {
        abort_unless((bool) config('api.docs_enabled'), 404);
        abort_if(! $settings->isPublic() && ! auth()->check(), 401);
    }

    public function render(): View
    {
        return view('livewire.admin.api-documentation', [
            'rateLimit' => (int) config('api.rate_limit_per_minute'),
            'maxPerPage' => (int) config('api.max_per_page'),
        ])->layout(auth()->check() ? 'layouts.app' : 'layouts.guest', ['pageTitle' => 'Documentación API']);
    }
}
