<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DesignSystem extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403);
    }

    public function render()
    {
        return view('livewire.admin.design-system')->layout('layouts.app', ['pageTitle' => 'CodeRED Design System']);
    }
}
