<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ApiRequestLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Actividad reciente del usuario en CodeRED Mobile.
 *
 * No inventa un registro nuevo: reutiliza `api_request_logs`, la auditoría que
 * ya escribe AuditApiRequest en cada llamada. La atribución sale del token —los
 * tokens personales pertenecen a un usuario—, así que cada quien ve únicamente
 * lo suyo.
 *
 * Sólo se devuelven llamadas que salieron bien: un 403 o un 500 son ruido para
 * quien mira "qué hice hoy", y ya se auditan aparte para diagnóstico.
 */
class ActivityController
{
    /** Etiqueta legible por servicio auditado. */
    private const LABELS = [
        'ruc' => 'Consulta RUC',
        'dni' => 'Consulta DNI',
        'agencias' => 'Consulta de agencias',
        'declaraciones' => 'Declaración jurada',
        'admin' => 'Administración',
    ];

    /**
     * Permiso RBAC que debe conservar el usuario para seguir viendo cada
     * servicio en su actividad. Si se lo retiran, la entrada desaparece: la
     * lista no puede delatar módulos a los que ya no tiene acceso.
     */
    private const PERMISSIONS = [
        'ruc' => 'ruc.view',
        'dni' => 'dni-records.view',
        'agencias' => 'agencies.view',
        'declaraciones' => 'declaracion-jurada.view',
        'admin' => 'api-tokens.view-any',
    ];

    private const MAX_ITEMS = 20;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $limit = min(max((int) $request->integer('limit', 5), 1), self::MAX_ITEMS);

        // Servicios que el usuario todavía puede ver hoy.
        $visible = collect(self::PERMISSIONS)
            ->filter(fn (string $permission): bool => $user->hasPermission($permission))
            ->keys()
            ->all();

        if ($visible === []) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // Los tokens personales del usuario son la vía de atribución: la
        // auditoría guarda el token, no el usuario, en las rutas móviles.
        $tokenIds = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->getKey())
            ->pluck('id');

        if ($tokenIds->isEmpty()) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $logs = ApiRequestLog::query()
            ->whereIn('token_id', $tokenIds)
            ->whereIn('service', $visible)
            ->whereBetween('status_code', [200, 299])
            ->latest('created_at')
            ->limit($limit)
            ->get(['service', 'method', 'status_code', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $logs->map(fn (ApiRequestLog $log): array => [
                'servicio' => $log->service,
                'titulo' => self::LABELS[$log->service] ?? 'Actividad',
                // Nunca el endpoint ni el identificador consultado: un DNI o un
                // RUC en claro no tiene por qué viajar en un resumen de actividad.
                'ocurrido_en' => $log->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
