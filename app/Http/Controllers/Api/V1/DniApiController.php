<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dni\Exceptions\DniProviderUnavailableException;
use App\Http\Requests\Api\V1\DniRequest;
use App\Http\Resources\Api\V1\DniResource;
use App\Services\Dni\DniLookupService;
use Illuminate\Http\JsonResponse;

class DniApiController
{
    public function __invoke(DniRequest $request, DniLookupService $service): DniResource|JsonResponse
    {
        try {
            $result = $service->find((string) $request->validated('dni'));
        } catch (DniProviderUnavailableException) {
            return response()->json(['success' => false, 'message' => 'El servicio de consulta DNI no está disponible temporalmente.'], 503);
        }
        if ($result === null) {
            return response()->json(['success' => false, 'message' => 'No se encontraron datos para el DNI consultado.'], 404);
        }

        return new DniResource($result);
    }
}
