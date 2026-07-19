<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Services\AgencySearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class Map extends Component
{
    private const MAX_MARKERS = 1000;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $department = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Agency::class);
    }

    public function render(AgencySearchService $searchService): View
    {
        $filters = ['search' => $this->search, 'status' => $this->status, 'department' => $this->department];
        $baseQuery = $searchService->adminQuery($filters);
        $mappedQuery = (clone $baseQuery)->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [-90, 90])->whereBetween('longitude', [-180, 180]);
        $total = (clone $mappedQuery)->count();
        $agencies = $mappedQuery->orderBy('department')->orderBy('name')->limit(self::MAX_MARKERS)->get([
            'id', 'code', 'name', 'department', 'province', 'district', 'address', 'latitude', 'longitude', 'status',
        ]);
        $withoutCoordinatesQuery = (clone $baseQuery)->where(function (Builder $query): void {
            $query->whereNull('latitude')->orWhereNull('longitude')
                ->orWhereNotBetween('latitude', [-90, 90])->orWhereNotBetween('longitude', [-180, 180]);
        });

        $markers = $agencies->map(fn (Agency $agency): array => [
            'id' => $agency->id,
            'code' => $agency->code,
            'name' => $agency->name,
            'department' => $agency->department,
            'province' => $agency->province,
            'district' => $agency->district,
            'location' => collect([$agency->district, $agency->province, $agency->department])->filter()->join(', '),
            'address' => $agency->address,
            'latitude' => (float) $agency->latitude,
            'longitude' => (float) $agency->longitude,
            'status' => $agency->status->value,
            'status_label' => $agency->statusLabel(),
            'detail_url' => route('admin.agencies.show', $agency),
            'maps_url' => sprintf('https://www.google.com/maps/search/?api=1&query=%s,%s', $agency->latitude, $agency->longitude),
        ])->values()->all();

        return view('livewire.admin.agencies.map', [
            'markers' => $markers,
            'mapKey' => sha1(json_encode($markers, JSON_THROW_ON_ERROR)),
            'mappedCount' => $agencies->count(),
            'totalMatching' => $total,
            'withoutCoordinates' => (clone $withoutCoordinatesQuery)->count(),
            'unmappedAgencies' => $withoutCoordinatesQuery->orderBy('name')->limit(8)->get(['id', 'code', 'name']),
            'departments' => Agency::query()->whereNotNull('latitude')->whereNotNull('longitude')->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'statuses' => ['' => 'Todos'] + AgencyStatus::options(),
            'markerLimit' => self::MAX_MARKERS,
        ])->layout('layouts.app', ['pageTitle' => 'Mapa de agencias']);
    }
}
