<?php

namespace App\Livewire\PublicAgencies;

use App\Modules\Agencies\Models\Agency;
use Livewire\Component;

class Show extends Component
{
    public Agency $agency;

    public function mount(string $code): void
    {
        $this->agency = Agency::query()
            ->where('code', strtoupper($code))
            ->firstOrFail();
    }

    public function render()
    {
        return view('livewire.public.agencies.show')->layout('layouts.guest');
    }
}
