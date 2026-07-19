<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Enums\AgencySize;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Services\AgencySearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
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
    public string $size = '';

    #[Url]
    public string $source = '';

    #[Url]
    public string $operationsCenter = '';

    #[Url]
    public string $moved = '';

    #[Url]
    public string $withoutCoordinates = '';

    #[Url]
    public string $withoutPhone = '';

    #[Url]
    public string $underReview = '';

    #[Url]
    public string $withTrashed = '';

    #[Url]
    public int $perPage = 15;

    public string $sortField = 'updated_at';

    public string $sortDirection = 'desc';

    public function mount(): void
    {
        Gate::authorize('viewAny', Agency::class);
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortField = $field;
        $this->sortDirection = 'asc';
    }

    public function deleteAgency(int $agencyId): void
    {
        $agency = Agency::query()->findOrFail($agencyId);
        Gate::authorize('delete', $agency);
        $agency->delete();

        $this->dispatch('toast', type: 'success', message: 'La agencia se movió a la papelera.');
    }

    public function restoreAgency(int $agencyId): void
    {
        $agency = Agency::onlyTrashed()->findOrFail($agencyId);
        Gate::authorize('restore', $agency);
        $agency->restore();

        $this->dispatch('toast', type: 'success', message: 'La agencia fue restaurada.');
    }

    public function forceDeleteAgency(int $agencyId): void
    {
        $agency = Agency::onlyTrashed()->findOrFail($agencyId);
        Gate::authorize('forceDelete', $agency);
        $agency->forceDelete();

        $this->dispatch('toast', type: 'success', message: 'La agencia fue eliminada definitivamente.');
    }

    public function render(AgencySearchService $searchService): View
    {
        $allowedSortFields = ['code', 'name', 'department', 'province', 'district', 'updated_at', 'data_version'];
        if (! in_array($this->sortField, $allowedSortFields, true)) {
            $this->sortField = 'updated_at';
        }

        $filters = [
            'search' => $this->search,
            'status' => $this->status,
            'department' => $this->department,
            'province' => $this->province,
            'district' => $this->district,
            'size' => $this->size,
            'source' => $this->source,
            'operations_center' => $this->operationsCenter,
            'moved' => $this->moved,
            'without_coordinates' => $this->withoutCoordinates,
            'without_phone' => $this->withoutPhone,
            'under_review' => $this->underReview,
            'trash' => $this->withTrashed,
        ];

        $agencies = $searchService->adminQuery($filters)
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.agencies.index', [
            'agencies' => $agencies,
            'stats' => [
                'total' => Agency::query()->count(),
                'active' => Agency::query()->where('status', AgencyStatus::Active)->count(),
                'under_review' => Agency::query()->where('status', AgencyStatus::UnderReview)->count(),
                'moved' => Agency::query()->moved()->count(),
                'operations_centers' => Agency::query()->operationsCenters()->count(),
                'trash' => Agency::onlyTrashed()->count(),
            ],
            'departments' => Agency::withTrashed()->select('department')->distinct()->orderBy('department')->pluck('department'),
            'provinces' => Agency::withTrashed()->select('province')->distinct()->orderBy('province')->pluck('province'),
            'districts' => Agency::withTrashed()->select('district')->distinct()->orderBy('district')->pluck('district'),
            'sizes' => ['' => 'Todos'] + AgencySize::options(),
            'statuses' => ['' => 'Todos'] + AgencyStatus::options(),
        ])->layout('layouts.app', ['pageTitle' => 'Agencias Shalom']);
    }
}
