<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Services\CatalogRevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CatalogMetadataController
{
    public function __invoke(Request $request, CatalogRevisionService $revision): JsonResponse|Response
    {
        $metadata = $revision->metadata();
        $etag = $revision->etag();
        if ($revision->isNotModified($request, $etag)) {
            return $revision->notModified($etag, $metadata['last_changed_at']);
        }

        return response()->json([
            'schema_version' => (int) config('api.agency_schema_version'),
            'catalog_revision' => $metadata['catalog_revision'],
            'generated_at' => $metadata['last_changed_at'] ?? '1970-01-01T00:00:00+00:00',
            'total_agencies' => $metadata['total'],
            'last_agency_update' => $metadata['last_update'],
            'last_agency_deletion' => $metadata['last_deletion'],
            'current_cursor' => $metadata['current_cursor'],
            'supports_incremental_sync' => true,
            'changes_endpoint' => '/api/v1/agencies/changes',
            'full_sync_required_after_days' => (int) config('api.agency_changelog_retention_days'),
            'available_statuses' => array_column(AgencyStatus::cases(), 'value'),
            'available_status_options' => array_map(
                static fn (AgencyStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                AgencyStatus::cases(),
            ),
            'supports_operations_center' => true,
            'available_channels' => ['terrestrial', 'air'],
        ], 200, $revision->headers($etag, $metadata['last_changed_at']));
    }
}
