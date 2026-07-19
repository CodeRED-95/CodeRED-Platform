<?php

namespace App\Livewire\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ApiDocumentation extends Component
{
    public function mount(): void
    {
        abort_unless((bool) config('api.docs_enabled'), 404);
        if ((bool) config('api.docs_require_auth')) {
            Gate::authorize('api-tokens.view-any');
        }
    }

    public function render(): View
    {
        return view('livewire.admin.api-documentation', [
            'rateLimit' => (int) config('api.rate_limit_per_minute'),
            'maxPerPage' => (int) config('api.max_per_page'),
        ])->layout(auth()->check() ? 'layouts.app' : 'layouts.guest', ['pageTitle' => 'Documentación API']);
    }
}
