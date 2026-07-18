<?php

namespace App\Livewire\Admin;

use App\Modules\Agencies\Models\Agency;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class DesignSystem extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', Agency::class);
    }

    public function render()
    {
        return view('livewire.admin.design-system')->layout('layouts.app', ['pageTitle' => 'CodeRED Design System']);
    }
}
