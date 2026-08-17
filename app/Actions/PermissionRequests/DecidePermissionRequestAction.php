<?php

declare(strict_types=1);

namespace App\Actions\PermissionRequests;

use App\Enums\PermissionRequestStatus;
use App\Exceptions\PermissionRequestTransitionException;
use App\Models\PermissionRequest;
use App\Models\User;
use App\Notifications\MobileAccessDecided;
use App\Services\Permissions\MobileAccessManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aprueba o rechaza una solicitud de acceso móvil.
 *
 * Todo ocurre dentro de una transacción con la fila bloqueada, y en un orden
 * que importa: **primero se concede el permiso y sólo entonces se marca la
 * solicitud como aprobada**. Si la asignación fallara, la transacción vuelve
 * atrás y la solicitud sigue pendiente; nunca queda una solicitud aprobada
 * cuyo permiso no llegó a otorgarse.
 *
 * El bloqueo resuelve el caso de dos administradores decidiendo a la vez: el
 * segundo encuentra la solicitud ya resuelta y recibe un error explícito en
 * lugar de conceder el permiso por segunda vez o pisar la decisión del primero.
 */
final class DecidePermissionRequestAction
{
    public function __construct(private readonly MobileAccessManager $access) {}

    /**
     * @throws PermissionRequestTransitionException si ya estaba resuelta
     */
    public function approve(PermissionRequest $request, User $reviewer): PermissionRequest
    {
        return $this->decide($request, $reviewer, PermissionRequestStatus::Approved, null);
    }

    /**
     * @throws PermissionRequestTransitionException si ya estaba resuelta
     */
    public function reject(PermissionRequest $request, User $reviewer, ?string $reason): PermissionRequest
    {
        return $this->decide($request, $reviewer, PermissionRequestStatus::Rejected, $reason);
    }

    private function decide(
        PermissionRequest $request,
        User $reviewer,
        PermissionRequestStatus $status,
        ?string $reason
    ): PermissionRequest {
        $decidida = DB::transaction(function () use ($request, $reviewer, $status, $reason): PermissionRequest {
            /** @var PermissionRequest $fresca */
            $fresca = PermissionRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresca->status->isOpen()) {
                throw new PermissionRequestTransitionException(sprintf(
                    'La solicitud ya está %s.',
                    mb_strtolower($fresca->status->label())
                ));
            }

            // Nadie decide sobre lo suyo, ni siquiera con permisos de gestión.
            if ($fresca->user_id === $reviewer->getKey()) {
                throw new PermissionRequestTransitionException('No puedes resolver tu propia solicitud.');
            }

            if ($status === PermissionRequestStatus::Approved) {
                $solicitante = User::query()->findOrFail($fresca->user_id);

                // Primero el permiso. Si esto lanza, la solicitud no se marca.
                $this->access->grant($solicitante, $fresca->permission);
            }

            $fresca->forceFill([
                'status' => $status,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->getKey(),
                'rejection_reason' => $status === PermissionRequestStatus::Rejected ? $reason : null,
            ])->save();

            return $fresca;
        });

        Log::info($status === PermissionRequestStatus::Approved ? 'permission_approved' : 'permission_rejected', [
            'permission_request_id' => $decidida->getKey(),
            'permission' => $decidida->permission,
            'user_id' => $decidida->user_id,
            'reviewed_by' => $reviewer->getKey(),
        ]);

        // Fuera de la transacción: avisar no puede hacer fallar la decisión.
        $decidida->loadMissing('user');
        $decidida->user?->notify(new MobileAccessDecided($decidida));

        return $decidida;
    }
}
