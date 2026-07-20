<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\DniRequest;
use App\Http\Resources\Api\V1\DniResource;
use App\Services\Dni\DniLookupService;
use Illuminate\Http\JsonResponse;

class DniApiController
{
    public function __invoke(DniRequest $request, DniLookupService $service): DniResource|JsonResponse
    {
        $result = $service->find((string) $request->validated('dni'));
        $request->route()?->setParameter('_dni_audit', json_encode($result->audit(), JSON_THROW_ON_ERROR));

        if ($result->status === 'not_found') {
            return response()->json(['success' => false, 'message' => $result->message], 404);
        }
        if ($result->status === 'invalid_response') {
            return response()->json(['success' => false, 'message' => $result->message], 502);
        }
        if ($result->status !== 'found' || $result->data === null) {
            return response()->json(['success' => false, 'message' => $result->message], 503);
        }

        return (new DniResource($result->data))->additional(['meta' => ['source' => $result->source]]);
    }
}
