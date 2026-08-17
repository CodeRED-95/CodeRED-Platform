<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\ApiTokenRequests\ApproveTokenRequestAction;
use App\Actions\ApiTokenRequests\RejectTokenRequestAction;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenType;
use App\Exceptions\TokenRequestTransitionException;
use App\Http\Resources\Api\V1\Admin\AdminTokenRequestResource;
use App\Models\ApiTokenRequest;
use App\Models\User;
use App\Services\ApiTokens\ApiTokenGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solicitudes de token desde CodeRED Mobile.
 *
 * Aprobar y rechazar delegan en ApproveTokenRequestAction y
 * RejectTokenRequestAction, las mismas que usa el panel web: la emisión del
 * token, la bóveda, la auditoría y el aviso a n8n ocurren en el servidor y de
 * una sola forma. La app no genera tokens por su cuenta.
 */
class AdminTokenRequestController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizeAdmin($request, 'api-token-requests.view');

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $query = ApiTokenRequest::query()->with('reviewer')->latest('id');

        $estado = (string) $request->query('estado', '');

        if ($estado !== '' && ApiTokenRequestStatus::tryFrom($estado) !== null) {
            $query->where('status', $estado);
        }

        // El nombre del solicitante está cifrado en columna, así que no se puede
        // filtrar por SQL: la búsqueda va contra los campos en claro
        // (tracking code y aplicación), que es lo que se usa para localizarlas.
        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('tracking_code', 'like', '%'.$search.'%')
                    ->orWhere('application_name', 'like', '%'.$search.'%');
            });
        }

        return $this->paginated(
            AdminTokenRequestResource::collection($query->paginate($this->perPage($request))->withQueryString())
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->authorizeAdmin($request, 'api-token-requests.view');

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $solicitud = ApiTokenRequest::query()->with('reviewer')->find($id);

        if ($solicitud === null) {
            return $this->deny('La solicitud no existe.', Response::HTTP_NOT_FOUND);
        }

        return (new AdminTokenRequestResource($solicitud))->response();
    }

    public function approve(Request $request, int $id, ApproveTokenRequestAction $action): JsonResponse
    {
        $user = $this->authorizeAdmin($request, 'api-token-requests.approve');

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = $request->validate([
            'nombre_token' => ['required', 'string', 'max:100'],
            // Acepta uno o varios tipos: un token puede cubrir DNI y RUC a la vez.
            // Se admite todavia el valor suelto para no romper a quien ya llama asi.
            'tipo_token' => ['required'],
            'tipo_token.*' => ['string', Rule::in(ApiTokenType::values())],
            'vigencia_dias' => [
                'required', 'integer',
                'min:'.ApiTokenGenerator::MIN_EXPIRES_IN_DAYS,
                'max:'.ApiTokenGenerator::MAX_EXPIRES_IN_DAYS,
            ],
            'usuario_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);

        if (User::query()->active()->find($data['usuario_id']) === null) {
            return $this->deny('El usuario indicado no existe o no está activo.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $solicitud = $action->execute(
                requestId: $id,
                tokenName: $data['nombre_token'],
                tokenTypes: array_map(ApiTokenType::from(...), (array) $data['tipo_token']),
                tokenExpiresInDays: (int) $data['vigencia_dias'],
                ownerUserId: (int) $data['usuario_id'],
                actorId: $user->getKey(),
            );
        } catch (TokenRequestTransitionException $exception) {
            return $this->deny($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // El token queda cifrado en la bóveda para su entrega; la app no lo ve.
        return (new AdminTokenRequestResource($solicitud->fresh(['reviewer'])))
            ->additional(['message' => 'Solicitud aprobada. El token se entrega por el canal acordado, no desde la app.'])
            ->response();
    }

    public function reject(Request $request, int $id, RejectTokenRequestAction $action): JsonResponse
    {
        $user = $this->authorizeAdmin($request, 'api-token-requests.reject');

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = $request->validate([
            'motivo' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $solicitud = $action->execute($id, $data['motivo'] ?? null, $user->getKey());
        } catch (TokenRequestTransitionException $exception) {
            return $this->deny($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return (new AdminTokenRequestResource($solicitud->fresh(['reviewer'])))
            ->additional(['message' => 'Solicitud rechazada.'])
            ->response();
    }
}
