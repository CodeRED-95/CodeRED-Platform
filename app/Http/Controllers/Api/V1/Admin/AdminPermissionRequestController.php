<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\PermissionRequests\DecidePermissionRequestAction;
use App\Enums\PermissionRequestStatus;
use App\Exceptions\PermissionRequestTransitionException;
use App\Http\Resources\Api\V1\Admin\AdminPermissionRequestResource;
use App\Models\PermissionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bandeja de solicitudes de acceso móvil.
 *
 * Dos ejes de autorización, como en el resto del área de administración: la
 * ability del token abre la sección y el permiso RBAC se vuelve a comprobar
 * aquí en cada petición, porque un token emitido ayer conserva su ability
 * aunque a la persona le hayan retirado el permiso esta mañana.
 */
class AdminPermissionRequestController
{
    /** Ver la bandeja. */
    private const PERMISSION_VIEW = 'permission-requests.view';

    /** Aprobar o rechazar. */
    private const PERMISSION_MANAGE = 'permission-requests.manage';

    public function index(Request $request): JsonResponse
    {
        $admin = $this->authorizeFor($request, self::PERMISSION_VIEW);

        if ($admin instanceof JsonResponse) {
            return $admin;
        }

        $query = PermissionRequest::query()->with(['user.roles', 'reviewer']);

        $estado = $request->query('estado');

        if (is_string($estado) && $estado !== '') {
            $filtro = PermissionRequestStatus::tryFrom($estado);

            if ($filtro === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estado no reconocido.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $query->where('status', $filtro);
        }

        $search = $request->query('search');

        if (is_string($search) && trim($search) !== '') {
            $termino = '%'.trim($search).'%';

            $query->whereHas('user', function ($sub) use ($termino): void {
                $sub->where('name', 'ilike', $termino)->orWhere('email', 'ilike', $termino);
            });
        }

        // Las pendientes primero y, dentro, las más antiguas: quien lleva más
        // tiempo esperando se atiende antes.
        $page = $query
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('requested_at')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), (int) config('api.max_per_page')))
            ->withQueryString();

        $response = AdminPermissionRequestResource::collection($page)->response();

        $payload = $response->getData(true);
        $payload['success'] = true;
        // El contador viaja con la lista para que el badge no necesite otra
        // petición.
        $payload['meta']['pendientes'] = PermissionRequest::query()->pending()->count();
        $response->setData($payload);

        return $response;
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $admin = $this->authorizeFor($request, self::PERMISSION_VIEW);

        if ($admin instanceof JsonResponse) {
            return $admin;
        }

        $solicitud = PermissionRequest::query()->with(['user.roles', 'reviewer'])->find($id);

        if ($solicitud === null) {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud no existe.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => new AdminPermissionRequestResource($solicitud),
        ]);
    }

    public function approve(Request $request, int $id, DecidePermissionRequestAction $action): JsonResponse
    {
        return $this->decide($request, $id, fn (PermissionRequest $s, User $admin) => $action->approve($s, $admin));
    }

    public function reject(Request $request, int $id, DecidePermissionRequestAction $action): JsonResponse
    {
        $validated = $request->validate([
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->decide(
            $request,
            $id,
            fn (PermissionRequest $s, User $admin) => $action->reject($s, $admin, $validated['motivo'] ?? null)
        );
    }

    /**
     * @param  callable(PermissionRequest, User): PermissionRequest  $decision
     */
    private function decide(Request $request, int $id, callable $decision): JsonResponse
    {
        $admin = $this->authorizeFor($request, self::PERMISSION_MANAGE);

        if ($admin instanceof JsonResponse) {
            return $admin;
        }

        $solicitud = PermissionRequest::query()->find($id);

        if ($solicitud === null) {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud no existe.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $decidida = $decision($solicitud, $admin);
        } catch (PermissionRequestTransitionException $exception) {
            // Ya resuelta por otro administrador, o es la propia. Se responde
            // con el motivo exacto en lugar de un error genérico.
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        $decidida->load(['user.roles', 'reviewer']);

        return response()->json([
            'success' => true,
            'message' => sprintf('Solicitud %s.', mb_strtolower($decidida->status->label())),
            'data' => new AdminPermissionRequestResource($decidida),
        ]);
    }

    private function authorizeFor(Request $request, string $permission): User|JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para esta acción.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $user;
    }
}
