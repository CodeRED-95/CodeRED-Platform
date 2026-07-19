<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class CatalogMetadataController
{
    public function __invoke(): JsonResponse
    {
        $updatedAt = Agency::query()->max('updated_at');

        return response()->json([
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'total_agencies' => Agency::query()->count(),
            'last_agency_update' => $updatedAt ? Carbon::parse($updatedAt)->toIso8601String() : null,
            'available_statuses' => array_column(AgencyStatus::cases(), 'value'),
            'available_channels' => ['terrestrial', 'air'],
        ]);
    }
}
