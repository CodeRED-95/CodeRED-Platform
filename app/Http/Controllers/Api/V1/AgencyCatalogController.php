<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\AgencyIndexRequest;
use App\Http\Resources\Api\V1\AgencyResource;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AgencyCatalogController
{
    public function index(AgencyIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = Agency::query()
            ->search($filters['search'] ?? null)
            ->byLocation($filters['department'] ?? null, $filters['province'] ?? null, $filters['district'] ?? null)
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['size']), fn (Builder $query) => $query->where('size', $filters['size']))
            ->when(array_key_exists('has_terrestrial', $filters), fn (Builder $query) => $this->withChannel($query, 'texto_chosen_terrestre', (bool) $filters['has_terrestrial']))
            ->when(array_key_exists('has_air', $filters), fn (Builder $query) => $this->withChannel($query, 'texto_chosen_aereo', (bool) $filters['has_air']));

        $sort = $filters['sort'] ?? 'code';
        $direction = $filters['direction'] ?? 'asc';
        $perPage = (int) ($filters['per_page'] ?? min(50, (int) config('api.max_per_page')));

        return AgencyResource::collection($query->orderBy($sort, $direction)->paginate($perPage)->withQueryString());
    }

    public function show(string $code): AgencyResource
    {
        $agency = Agency::query()->where('code', strtoupper(trim($code)))->firstOrFail();

        return new AgencyResource($agency);
    }

    private function withChannel(Builder $query, string $column, bool $present): Builder
    {
        return $present
            ? $query->whereNotNull($column)->where($column, '<>', '')
            : $query->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, ''));
    }
}
