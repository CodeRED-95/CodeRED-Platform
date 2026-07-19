<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyChangeLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
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

    public function render(): View
    {
        $canViewHistory = Gate::allows('viewHistory', $this->agency);
        $history = $canViewHistory
            ? AgencyChangeLog::query()
                ->with('actor')
                ->where('agency_id', $this->agency->id)
                ->latest('created_at')
                ->limit(25)
                ->get()
            : new Collection;

        return view('livewire.admin.agencies.show', [
            'history' => $history,
            'canViewHistory' => $canViewHistory,
        ])->layout('layouts.app', ['pageTitle' => $this->agency->name]);
    }
}
