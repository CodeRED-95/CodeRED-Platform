<?php

namespace App\Modules\Agencies\Http\Controllers;

use App\Http\Requests\Agencies\PreviewAgencyImportRequest;
use App\Modules\Agencies\Services\AgencyImportPreviewService;
use Illuminate\Http\JsonResponse;

class AgencyImportPreviewController
{
    public function __invoke(PreviewAgencyImportRequest $request, AgencyImportPreviewService $service): JsonResponse
    {
        $payload = $service->previewFromUrl($request->validated('url'));

        return response()->json([
            'success' => true,
            'data' => $payload,
            'meta' => [],
        ]);
    }
}
