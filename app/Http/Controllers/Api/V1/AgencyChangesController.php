<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\AgencyChangesRequest;
use App\Modules\Agencies\Exceptions\InvalidAgencySyncCursorException;
use App\Modules\Agencies\Models\AgencySyncChange;
use App\Modules\Agencies\Services\AgencySyncCursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgencyChangesController
{
    public function __invoke(AgencyChangesRequest $request, AgencySyncCursor $cursor): JsonResponse
    {
        try {
            $sequence = $cursor->decode((string) $request->validated('cursor'));
        } catch (InvalidAgencySyncCursorException) {
            return $this->fullSyncRequired();
        }

        $minimumSequence = (int) (DB::table('agency_sync_states')->where('id', 1)->value('minimum_sequence') ?? 0);
        if ($sequence < $minimumSequence) {
            return $this->fullSyncRequired();
        }

        $limit = (int) ($request->validated('limit') ?? config('api.agency_changes_default_limit'));
        $changes = AgencySyncChange::query()
            ->where('id', '>', $sequence)
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();
        $hasMore = $changes->count() > $limit;
        $page = $changes->take($limit);
        $lastSequence = (int) ($page->last()?->getKey() ?? $sequence);

        $latestByAgency = $page
            ->reverse()
            ->unique('agency_internal_id')
            ->sortBy('id')
            ->values();
        $upserted = $latestByAgency->where('operation', 'upsert')->pluck('payload')->filter()->values()->all();
        $deleted = $latestByAgency->where('operation', 'delete')->map(fn (AgencySyncChange $change): array => [
            'internal_id' => $change->agency_internal_id,
            'id' => $change->external_id,
            'code' => $change->code,
            'deleted_at' => $change->changed_at?->utc()->toIso8601String(),
        ])->values()->all();

        Log::debug('agency_incremental_sync', [
            'processed' => $page->count(),
            'returned' => $latestByAgency->count(),
            'upserted' => count($upserted),
            'deleted' => count($deleted),
            'has_more' => $hasMore,
        ]);

        return response()->json([
            'data' => ['upserted' => $upserted, 'deleted' => $deleted],
            'meta' => [
                'next_cursor' => $cursor->encode($lastSequence),
                'has_more' => $hasMore,
                'schema_version' => (int) config('api.agency_schema_version'),
                'generated_at' => now()->utc()->toIso8601String(),
            ],
        ], 200, [
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Authorization, Accept-Encoding',
        ]);
    }

    private function fullSyncRequired(): JsonResponse
    {
        Log::notice('agency_sync_cursor_invalidated', [
            'schema_version' => (int) config('api.agency_schema_version'),
        ]);

        return response()->json([
            'message' => 'El cursor ya no es válido. Se requiere una sincronización completa.',
            'code' => 'full_sync_required',
            'meta' => ['schema_version' => (int) config('api.agency_schema_version')],
        ], 409, [
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Authorization, Accept-Encoding',
        ]);
    }
}
