<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SunatRepresentativeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RucRepresentativeRequest;
use App\Services\Sunat\SunatRepresentativeService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

class RucRepresentativeController extends Controller
{
    public function __invoke(RucRepresentativeRequest $request, SunatRepresentativeService $service): JsonResponse
    {
        abort_unless(config('api.enabled'), 503, 'La API está deshabilitada.');

        try {
            $result = $service->find($request->validated('ruc'));
        } catch (ConnectionException $e) {
            $status = str_contains(mb_strtolower($e->getMessage()), 'timed out') ? 504 : 503;

            return response()->json(['success' => false, 'message' => $status === 504 ? 'Timeout consultando SUNAT.' : 'SUNAT no está disponible.'], $status);
        } catch (RequestException $e) {
            $status = $e->response?->status() >= 500 ? 503 : 502;

            return response()->json([
                'success' => false,
                'message' => $status === 503 ? 'SUNAT no está disponible.' : 'SUNAT respondió de forma inválida.',
            ], $status);
        } catch (SunatRepresentativeException) {
            return response()->json(['success' => false, 'message' => 'SUNAT respondió de forma inválida.'], 502);
        }

        if ($result['data'] === null) {
            return response()->json(['success' => false, 'message' => 'No se encontró información de representantes legales para el RUC consultado.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ruc' => $request->validated('ruc'),
                'representantes' => array_map(fn ($item) => $item->toArray(), $result['data']),
            ],
            'meta' => [
                'source' => $result['source'],
                'cached' => $result['cached'],
                'consulted_at' => $result['consulted_at'],
            ],
        ]);
    }
}
