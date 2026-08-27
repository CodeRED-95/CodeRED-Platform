<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\DniNameSearchRequest;
use App\Services\DniNameSearch\DniNameSearchService;
use Illuminate\Http\JsonResponse;

final class DniNameSearchController
{
    public function __invoke(DniNameSearchRequest $request, DniNameSearchService $service): JsonResponse
    {
        $result = $service->search(
            (string) $request->validated('nombres'),
            (string) $request->validated('apellido_paterno'),
            (string) $request->validated('apellido_materno'),
        );

        $request->route()?->setParameter('_dni_name_audit', json_encode([
            'source' => $result->status === 'found' ? 'dniperu' : null,
            'provider_called' => $result->status !== 'provider_disabled' && ! $result->cacheHit,
            'provider_status_code' => $result->statusCode,
            'cache_hit' => $result->cacheHit,
            'local_database_hit' => false,
        ], JSON_THROW_ON_ERROR));

        return match ($result->status) {
            'found' => response()->json([
                'success' => true,
                'data' => array_map(fn ($match) => $match->toArray(), $result->matches),
                'meta' => [
                    'provider' => 'dniperu',
                    'official' => false,
                    'referential' => true,
                    'count' => count($result->matches),
                ],
            ]),
            'not_found' => response()->json(['success' => false, 'message' => $result->message, 'data' => []], 404),
            'rate_limited' => response()->json(['success' => false, 'message' => $result->message], 429),
            'provider_blocked' => response()->json(['success' => false, 'message' => $result->message], 503),
            default => response()->json(['success' => false, 'message' => $result->message ?? 'El proveedor no está disponible.'], 503),
        };
    }
}
