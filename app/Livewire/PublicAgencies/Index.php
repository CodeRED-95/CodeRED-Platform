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

    #[Url]
    public string $department = '';

    #[Url]
    public string $province = '';

    #[Url]
    public string $district = '';

    public function render(AgencySearchService $searchService)
    {
        $agencies = $searchService->publicQuery([
                'search' => $this->search,
                'department' => $this->department,
                'province' => $this->province,
                'district' => $this->district,
            ])
            ->latest('updated_at')
            ->paginate(12);

        return view('livewire.public.agencies.index', [
            'agencies' => $agencies,
            'departments' => \App\Modules\Agencies\Models\Agency::query()->select('department')->distinct()->orderBy('department')->pluck('department'),
            'provinces' => \App\Modules\Agencies\Models\Agency::query()->select('province')->distinct()->orderBy('province')->pluck('province'),
            'districts' => \App\Modules\Agencies\Models\Agency::query()->select('district')->distinct()->orderBy('district')->pluck('district'),
        ])->layout('layouts.guest');
    }
}
