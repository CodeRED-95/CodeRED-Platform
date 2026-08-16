<?php

declare(strict_types=1);

namespace App\Actions\ApiTokenRequests;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Exceptions\TokenRequestTransitionException;
use App\Jobs\NotifyN8nTokenRequestStatus;
use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;

/**
 * Rechaza una solicitud de token.
 *
 * Compartida por el panel Livewire y la API de administración móvil, para que
 * un rechazo deje exactamente el mismo rastro venga de donde venga: motivo
 * registrado aparte del evento de rechazo, entrega marcada como no disponible y
 * el texto cifrado del token borrado si lo hubiera.
 *
 * Quien llama es responsable de la autorización (`api-token-requests.reject`).
 */
class RejectTokenRequestAction
{
    /**
     * @throws TokenRequestTransitionException si la solicitud ya no es rechazable
     */
    public function execute(int $requestId, ?string $reason, ?int $actorId): ApiTokenRequest
    {
        $request = ApiTokenRequest::query()->findOrFail($requestId);

        if ($request->status !== ApiTokenRequestStatus::Pending) {
            $this->event($request, 'invalid_transition', 'Intento invalido de rechazar una solicitud no pendiente.', ['status' => $request->statusValue()], $actorId);

            throw TokenRequestTransitionException::alreadyProcessed();
        }

        $trimmed = trim((string) $reason);

        $request->forceFill([
            'status' => ApiTokenRequestStatus::Rejected,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
            'rejected_at' => now(),
            'rejection_reason' => $trimmed === '' ? null : $trimmed,
            'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
            'token_ciphertext' => null,
        ])->save();

        $this->event($request, 'rejected', 'Solicitud rechazada.', [], $actorId);

        if ($trimmed !== '') {
            $this->event($request, 'rejection_reason_recorded', 'Motivo del rechazo registrado.', ['reason' => $trimmed], $actorId);
        }

        NotifyN8nTokenRequestStatus::dispatch($request->id, 'token_request.rejected');

        return $request;
    }

    /** @param array<string, mixed> $metadata */
    private function event(ApiTokenRequest $request, string $event, string $description, array $metadata, ?int $actorId): void
    {
        ApiTokenRequestEvent::query()->create([
            'api_token_request_id' => $request->id,
            'event' => $event,
            'description' => $description,
            'metadata' => $metadata,
            'performed_by' => $actorId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
