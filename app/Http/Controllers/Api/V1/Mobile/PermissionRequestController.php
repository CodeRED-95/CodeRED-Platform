<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Enums\PermissionRequestStatus;
use App\Http\Requests\Api\V1\StorePermissionRequestRequest;
use App\Http\Resources\Api\V1\PermissionRequestResource;
use App\Models\PermissionRequest;
use App\Models\User;
use App\Services\Permissions\MobileAccess;
use App\Services\Permissions\MobileAccessManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solicitudes de acceso a módulos móviles, desde el punto de vista de quien
 * las pide.
 *
 * Todo parte de `$user->permissionRequests()`: nadie puede ver ni tocar las
 * solicitudes de otro, y no hay comprobación de propiedad que se pueda olvidar
 * porque la consulta nunca sale de lo suyo.
 */
class PermissionRequestController
{
    public function __construct(private readonly MobileAccessManager $access) {}

    /**
     * Estado de los accesos móviles del usuario: cuáles tiene y, para los que
     * no, si hay una solicitud en curso o resuelta.
     *
     * La app pinta con esto las tarjetas bloqueadas, así que llega todo junto
     * en una sola petición.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        // La última solicitud de cada permiso: es la que describe el estado
        // actual. Las anteriores son historia y no cambian lo que se ve.
        $ultimas = $user->permissionRequests()
            ->latest('requested_at')
            ->get()
            ->unique('permission');

        $accesos = [];

        foreach ($this->access->statusFor($user) as $estado) {
            $solicitud = $ultimas->firstWhere('permission', $estado['permission']);

            $accesos[] = [
                'permission' => $estado['permission'],
                'label' => $estado['label'],
                'description' => $estado['description'],
                'granted' => $estado['granted'],
                'request' => $solicitud === null
                    ? null
                    : (new PermissionRequestResource($solicitud))->toArray($request),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $accesos,
        ]);
    }

    /**
     * Pide acceso a un módulo.
     *
     * El permiso se valida contra la lista blanca en el Form Request: no se
     * puede pedir uno arbitrario manipulando la petición.
     */
    public function store(StorePermissionRequestRequest $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $permission = (string) $request->validated('permission');

        // Pedir lo que ya se tiene no es un error, pero tampoco crea nada.
        if ($user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => sprintf('Ya tienes acceso a %s.', MobileAccess::label($permission)),
            ], Response::HTTP_CONFLICT);
        }

        // Dos barreras para lo mismo, y las dos hacen falta.
        //
        // La consulta previa resuelve el caso normal -alguien vuelve a pulsar-
        // con un mensaje claro y sin provocar un error de base de datos. El
        // indice unico parcial cubre el que la consulta no puede ver: dos
        // peticiones simultaneas que la comprueban a la vez, las dos antes de
        // que ninguna haya insertado.
        if ($this->hasPending($user, $permission)) {
            return $this->alreadyPending();
        }

        try {
            // Punto de guardado propio: si el indice salta, se deshace solo
            // esta insercion. Sin el, en PostgreSQL la transaccion que la
            // envuelva quedaria abortada y arrastraria todo lo demas.
            $solicitud = DB::transaction(fn (): PermissionRequest => PermissionRequest::query()->create([
                'user_id' => $user->getKey(),
                'permission' => $permission,
                'status' => PermissionRequestStatus::Pending,
                'reason' => $request->validated('reason'),
                'requested_at' => now(),
            ]));
        } catch (QueryException $exception) {
            if (! $this->isDuplicatePending($exception)) {
                throw $exception;
            }

            return $this->alreadyPending();
        }

        Log::info('permission_requested', [
            'permission_request_id' => $solicitud->getKey(),
            'user_id' => $user->getKey(),
            'permission' => $permission,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud enviada. Un administrador la revisará.',
            'data' => new PermissionRequestResource($solicitud),
        ], Response::HTTP_CREATED);
    }

    private function hasPending(User $user, string $permission): bool
    {
        return $user->permissionRequests()
            ->where('permission', $permission)
            ->where('status', PermissionRequestStatus::Pending)
            ->exists();
    }

    private function alreadyPending(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Ya tienes una solicitud pendiente para este acceso.',
        ], Response::HTTP_CONFLICT);
    }

    private function isDuplicatePending(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'permission_requests_one_pending');
    }

    private function currentUser(Request $request): User|JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $user;
    }
}
