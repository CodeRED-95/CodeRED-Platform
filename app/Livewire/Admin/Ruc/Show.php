<?php

namespace App\Livewire\Admin\Ruc;

use App\Modules\Ruc\Models\RucRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component
{
    public RucRecord $record;

    public function mount(RucRecord $record): void
    {
        Gate::authorize('ruc.view');
        $this->record = $record;
    }

    public function render(): View
    {
        return view('livewire.admin.ruc.show')->layout('layouts.app', ['pageTitle' => 'Detalle RUC']);
    }
}
