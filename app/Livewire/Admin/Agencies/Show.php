<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyChangeLog;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component
{
    public Agency $agency;

    public function mount(Agency $agency): void
    {
        Gate::authorize('view', $agency);

        $this->agency = $agency->load(['createdBy', 'updatedBy', 'movedToAgency']);
    }

    public function render()
    {
        $history = AgencyChangeLog::query()
            ->where('agency_id', $this->agency->id)
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('livewire.admin.agencies.show', [
            'history' => $history,
        ])->layout('layouts.app');
    }
}
