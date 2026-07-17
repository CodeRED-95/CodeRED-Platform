<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Models\Agency;
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
    public string $status = '';

    #[Url]
    public string $department = '';

    #[Url]
    public string $province = '';

    #[Url]
    public string $district = '';

    #[Url]
    public int $perPage = 15;

    public function render(AgencySearchService $searchService)
    {
        $filters = [
            'search' => $this->search,
            'status' => $this->status,
            'department' => $this->department,
            'province' => $this->province,
            'district' => $this->district,
        ];

        $agencies = $searchService->adminQuery($filters)
            ->latest('updated_at')
            ->paginate($this->perPage);

        return view('livewire.admin.agencies.index', compact('agencies'))->layout('layouts.app');
    }
}
