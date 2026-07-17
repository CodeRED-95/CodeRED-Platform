<?php

namespace App\Livewire\PublicAgencies;

use App\Modules\Agencies\Services\AgencySearchService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function render(AgencySearchService $searchService)
    {
        $agencies = $searchService->publicQuery(['search' => $this->search])
            ->latest('updated_at')
            ->paginate(12);

        return view('livewire.public.agencies.index', compact('agencies'))->layout('layouts.guest');
    }
}
