<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Modules\ExtensionControl\Services\ExtensionBlockRuleService;
use Illuminate\Http\JsonResponse;

/**
 * Reglas de bloqueo horario que la extension aplica sobre shalomcontrol.com.
 *
 * Sin ability especifica: cualquier token valido de la extension debe poder
 * leerlas, porque la configuracion es global y exigir un scope nuevo dejaria
 * sin bloqueo a todas las instalaciones ya emitidas.
 */
class ExtensionBlockRulesController
{
    public function __invoke(ExtensionBlockRuleService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service->payload(),
        ])->header('Cache-Control', 'private, max-age=60')->header('X-Content-Type-Options', 'nosniff');
    }
}
