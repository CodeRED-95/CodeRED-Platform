<?php

namespace App\Modules\Ruc\Http\Controllers;

use App\Modules\Ruc\Http\Requests\RucLookupRequest;
use App\Modules\Ruc\Http\Resources\RucResource;
use App\Modules\Ruc\Services\RucLookupService;

class RucApiController
{
    public function __invoke(RucLookupRequest $request, RucLookupService $service)
    {
        abort_unless(config('ruc.enabled'), 503, 'El servicio RUC está deshabilitado.');
        $started = hrtime(true);
        $result = $service->find($request->validated('ruc'));
        $request->route()->setParameter('_ruc_audit', json_encode(['source' => $result['source'], 'cache_hit' => $result['cached'], 'local_database_hit' => ! $result['cached']], JSON_THROW_ON_ERROR));
        if ($result['data'] === null) {
            return response()->json(['success' => false, 'message' => 'No se encontró el RUC consultado.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'RUC encontrado.', 'data' => (new RucResource($result['data']))->resolve($request), 'meta' => ['source' => $result['source'], 'cached' => $result['cached'], 'response_time_ms' => (int) round((hrtime(true) - $started) / 1_000_000)]]);
    }
}
