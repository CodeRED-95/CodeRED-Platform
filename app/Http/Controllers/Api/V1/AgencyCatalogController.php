<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\AgencyIndexRequest;
use App\Http\Resources\Api\V1\AgencyResource;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Services\CatalogRevisionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AgencyCatalogController
{
    public function index(AgencyIndexRequest $request, CatalogRevisionService $revision): JsonResponse|Response
    {
        $filters = $request->validated();
        $sort = $filters['sort'] ?? 'code';
        $direction = $filters['direction'] ?? 'asc';
        $perPage = (int) ($filters['per_page'] ?? min(50, (int) config('api.max_per_page')));
        $signature = [...$filters, 'sort' => $sort, 'direction' => $direction, 'per_page' => $perPage, 'page' => (int) ($filters['page'] ?? 1)];
        ksort($signature);
        $etag = $revision->etag('agencies:'.hash('sha256', json_encode($signature, JSON_THROW_ON_ERROR)));
        $metadata = $revision->metadata();
        if ($revision->isNotModified($request, $etag)) {
            return $revision->notModified($etag, $metadata['last_changed_at']);
        }

        $query = Agency::query()
            ->search($filters['search'] ?? null)
            ->byLocation($filters['department'] ?? null, $filters['province'] ?? null, $filters['district'] ?? null)
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['size']), fn (Builder $query) => $query->where('size', $filters['size']))
            ->when(array_key_exists('has_terrestrial', $filters), fn (Builder $query) => $this->withChannel($query, 'texto_chosen_terrestre', (bool) $filters['has_terrestrial']))
            ->when(array_key_exists('has_air', $filters), fn (Builder $query) => $this->withChannel($query, 'texto_chosen_aereo', (bool) $filters['has_air']));

        $response = AgencyResource::collection($query->orderBy($sort, $direction)->paginate($perPage)->withQueryString())->response();
        $payload = $response->getData(true);
        $payload['meta']['schema_version'] = (int) config('api.agency_schema_version');
        $response->setData($payload);
        $response->headers->add($revision->headers($etag, $metadata['last_changed_at']));

        return $response;
    }

    public function show(Request $request, string $code, CatalogRevisionService $revision): JsonResponse|Response
    {
        $normalizedCode = strtoupper(trim($code));
        $etag = $revision->etag('agency:'.$normalizedCode);
        $metadata = $revision->metadata();
        if ($revision->isNotModified($request, $etag)) {
            return $revision->notModified($etag, $metadata['last_changed_at']);
        }

        $resource = new AgencyResource(Agency::query()->where('code', $normalizedCode)->firstOrFail());
        $resource->additional(['meta' => ['schema_version' => (int) config('api.agency_schema_version')]]);
        $response = $resource->response();
        $response->headers->add($revision->headers($etag, $metadata['last_changed_at']));

        return $response;
    }

    private function withChannel(Builder $query, string $column, bool $present): Builder
    {
        return $present
            ? $query->whereNotNull($column)->where($column, '<>', '')
            : $query->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, ''));
    }
}
