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
        $status = $filters['estado'] ?? $filters['status'] ?? null;
        $size = $filters['tamano'] ?? $filters['size'] ?? null;
        $query = Agency::query()
            ->search($filters['agencia'] ?? $filters['search'] ?? null)
            ->byLocation($filters['departamento'] ?? $filters['department'] ?? null, $filters['provincia'] ?? $filters['province'] ?? null, $filters['distrito'] ?? $filters['district'] ?? null)
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($size !== null, fn (Builder $query) => $query->where('size', $size))
            ->when(array_key_exists('co', $filters), fn (Builder $query) => $query->where('is_operations_center', (bool) $filters['co']))
            ->when(array_key_exists('has_terrestrial', $filters), fn (Builder $query) => $this->withChannel($query, 'texto_chosen_terrestre', (bool) $filters['has_terrestrial']))
            ->when(array_key_exists('has_air', $filters), fn (Builder $query) => $this->withChannel($query, 'texto_chosen_aereo', (bool) $filters['has_air']));
        $response = AgencyResource::collection($query->orderBy($sort, $direction)->paginate($perPage)->withQueryString())->response();
        $payload = $response->getData(true);
        $payload['success'] = true;
        $payload['meta']['schema_version'] = (int) config('api.agency_schema_version');
        $response->setData($payload);
        $response->headers->add($revision->headers($etag, $metadata['last_changed_at']));

        return $response;
    }

    public function show(Request $request, string $code, CatalogRevisionService $revision): JsonResponse|Response
    {
        return $this->resourceResponse($request, Agency::query()->where('code', strtoupper(trim($code)))->firstOrFail(), $revision);
    }

    public function showById(Request $request, int $id, CatalogRevisionService $revision): JsonResponse|Response
    {
        return $this->resourceResponse($request, Agency::query()->findOrFail($id), $revision);
    }

    private function resourceResponse(Request $request, Agency $agency, CatalogRevisionService $revision): JsonResponse|Response
    {
        $etag = $revision->etag('agency:'.$agency->code);
        $metadata = $revision->metadata();
        if ($revision->isNotModified($request, $etag)) {
            return $revision->notModified($etag, $metadata['last_changed_at']);
        }
        $resource = new AgencyResource($agency);
        $resource->additional(['success' => true, 'meta' => ['schema_version' => (int) config('api.agency_schema_version')]]);
        $response = $resource->response();
        $response->headers->add($revision->headers($etag, $metadata['last_changed_at']));

        return $response;
    }

    private function withChannel(Builder $query, string $column, bool $present): Builder
    {
        return $present ? $query->whereNotNull($column)->where($column, '<>', '') : $query->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, ''));
    }
}
