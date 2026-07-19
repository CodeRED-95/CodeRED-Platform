<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Services\AgencySearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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
        $query = $searchService->adminQuery($filters)->whereNotNull('latitude')->whereNotNull('longitude');
        $total = (clone $query)->count();
        $agencies = $query->orderBy('department')->orderBy('name')->limit(self::MAX_MARKERS)->get([
            'id', 'code', 'name', 'department', 'province', 'district', 'address', 'latitude', 'longitude', 'status',
        ]);

        return view('livewire.admin.agencies.map', [
            'clusters' => $this->clusters($agencies),
            'mappedCount' => $agencies->count(),
            'totalMatching' => $total,
            'withoutCoordinates' => Agency::query()->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))->count(),
            'departments' => Agency::query()->whereNotNull('latitude')->whereNotNull('longitude')->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'statuses' => ['' => 'Todos'] + AgencyStatus::options(),
            'markerLimit' => self::MAX_MARKERS,
        ])->layout('layouts.app', ['pageTitle' => 'Mapa de agencias']);
    }

    /**
     * @param  Collection<int, Agency>  $agencies
     * @return array<int, array<string, mixed>>
     */
    private function clusters(Collection $agencies): array
    {
        return $agencies->groupBy(fn (Agency $agency): string => number_format((float) $agency->latitude, 1, '.', '').':'.number_format((float) $agency->longitude, 1, '.', ''))
            ->map(function (Collection $agencies): array {
                $latitude = (float) $agencies->avg(fn (Agency $agency): float => (float) $agency->latitude);
                $longitude = (float) $agencies->avg(fn (Agency $agency): float => (float) $agency->longitude);

                return [
                    'id' => 'cluster-'.$agencies->first()->id,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'top' => $this->percentage(-0.5 - $latitude, 18),
                    'left' => $this->percentage($longitude + 82.5, 14.5),
                    'count' => $agencies->count(),
                    'agencies' => $agencies->map(fn (Agency $agency): array => [
                        'code' => $agency->code,
                        'name' => $agency->name,
                        'location' => collect([$agency->district, $agency->province, $agency->department])->filter()->join(', '),
                        'address' => $agency->address,
                        'status' => $agency->status->value,
                        'status_label' => $agency->statusLabel(),
                        'detail_url' => route('admin.agencies.show', $agency),
                        'maps_url' => sprintf('https://www.google.com/maps/search/?api=1&query=%s,%s', $agency->latitude, $agency->longitude),
                    ])->values()->all(),
                ];
            })->values()->all();
    }

    private function percentage(float $value, float $range): float
    {
        return round(max(2, min(98, ($value / $range) * 100)), 2);
    }
}
