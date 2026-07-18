<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Resources\AgencyCollection;
use App\Modules\Agencies\Resources\AgencyResource;
use App\Modules\Agencies\Services\AgencySearchService;
use App\Modules\Agencies\Support\AgencyVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AgenciesController
{
    public function index(Request $request, AgencySearchService $search): AgencyCollection
    {
        $filters = $request->only(['page', 'per_page', 'department', 'province', 'district', 'status', 'updated_after', 'version', 'search']);
        $query = $search->publicQuery($filters);

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return new AgencyCollection($query->paginate($perPage));
    }

    public function search(Request $request, AgencySearchService $search): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $items = $search->publicQuery(['search' => $q])
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => AgencyResource::collection($items),
            'meta' => ['query' => $q],
        ]);
    }

    public function version(): JsonResponse
    {
        $updatedAt = Agency::query()->max('updated_at');

        return response()->json([
            'success' => true,
            'data' => [
                'version' => AgencyVersion::current(),
                'updated_at' => $updatedAt ? Carbon::parse($updatedAt)->toIso8601String() : null,
                'total_active' => Agency::query()->where('status', AgencyStatus::Active->value)->count(),
            ],
        ]);
    }

    public function show(string $code): JsonResponse
    {
        $agency = Agency::query()
            ->where('code', strtoupper($code))
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new AgencyResource($agency),
            'meta' => [],
        ]);
    }

    public function snapshot(Request $request): JsonResponse
    {
        $version = AgencyVersion::current();
        $etag = '"agency-snapshot-'.$version.'"';
        $lastModified = Agency::query()->max('updated_at');

        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->noContent(304)->header('ETag', $etag);
        }

        $agencies = Agency::query()
            ->where('status', AgencyStatus::Active->value)
            ->where('has_moved', false)
            ->orderBy('name')
            ->get(['code', 'name', 'short_name', 'slug', 'department', 'province', 'district', 'address', 'phone', 'secondary_phone', 'latitude', 'longitude', 'status', 'updated_at', 'has_moved', 'moved_to_agency_id', 'moved_to_address', 'move_notice', 'moved_at']);

        $movedAgencies = Agency::query()
            ->where('has_moved', true)
            ->orderBy('name')
            ->get(['code', 'name', 'moved_to_agency_id', 'moved_to_address', 'move_notice']);

        return response()->json([
            'success' => true,
            'version' => $version,
            'generated_at' => now()->toIso8601String(),
            'agencies' => $agencies,
            'moved_agencies' => $movedAgencies->map(fn (Agency $agency) => [
                'code' => $agency->code,
                'name' => $agency->name,
                'destination_code' => optional($agency->movedToAgency)->code,
                'destination_address' => $agency->moved_to_address,
                'notice' => $agency->move_notice,
            ])->values(),
        ])->header('ETag', $etag)->header('Last-Modified', $lastModified ? Carbon::parse($lastModified)->toRfc7231String() : now()->toRfc7231String());
    }
}
