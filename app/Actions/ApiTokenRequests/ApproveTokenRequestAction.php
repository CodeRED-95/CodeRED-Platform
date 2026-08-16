<?php

declare(strict_types=1);

namespace App\Actions\ApiTokenRequests;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenType;
use App\Exceptions\TokenRequestTransitionException;
use App\Jobs\NotifyN8nTokenRequestStatus;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use App\Models\User;
use App\Services\ApiTokens\ApiTokenGenerator;
use App\Services\ApiTokens\TelegramRequesterLinker;
use App\Services\ApiTokens\TokenVaultService;
use App\Services\Integrations\N8nTelegramTokenSettings;
use Illuminate\Support\Facades\DB;

/**
 * Aprueba una solicitud de token: emite el Sanctum, lo guarda cifrado en la
 * bóveda y deja la solicitud lista para entrega.
 *
 * Vive aquí y no dentro del panel Livewire porque la consumen dos frontales —el
 * panel web y la API de administración móvil— y una aprobación debe comportarse
 * exactamente igual desde ambos: mismo bloqueo de fila, mismas comprobaciones de
 * estado y vencimiento, misma auditoría y la misma notificación a n8n.
 *
 * Quien llama es responsable de la autorización (`api-token-requests.approve`);
 * esta acción sólo asume que ya se concedió.
 */
class ApproveTokenRequestAction
{
    public function __construct(
        private readonly N8nTelegramTokenSettings $settings,
        private readonly ApiTokenGenerator $generator,
        private readonly TokenVaultService $vault,
        private readonly TelegramRequesterLinker $linker,
    ) {}

    /**
     * @throws TokenRequestTransitionException si la solicitud ya no es aprobable
     */
    public function execute(
        int $requestId,
        string $tokenName,
        ApiTokenType $tokenType,
        int $tokenExpiresInDays,
        int $ownerUserId,
        ?int $actorId,
    ): ApiTokenRequest {
        $current = ApiTokenRequest::query()->findOrFail($requestId);

        if ($current->status !== ApiTokenRequestStatus::Pending) {
            $this->event($current, 'invalid_transition', 'Intento invalido de aprobar una solicitud no pendiente.', ['status' => $current->statusValue()], $actorId);

            throw TokenRequestTransitionException::alreadyProcessed();
        }

        return DB::transaction(function () use ($requestId, $tokenName, $tokenType, $tokenExpiresInDays, $ownerUserId, $actorId): ApiTokenRequest {
            // Bloqueo de fila: dos administradores aprobando a la vez no pueden
            // emitir dos tokens para la misma solicitud.
            $request = ApiTokenRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

            if ($request->status !== ApiTokenRequestStatus::Pending) {
                throw TokenRequestTransitionException::alreadyProcessed();
            }

            $requestedAt = $request->requestedAt();

            if ($requestedAt?->lt(now()->subMinutes((int) $this->settings->get('approval_timeout_minutes', 1440)))) {
                $this->event($request, 'invalid_transition', 'Intento invalido de aprobar una solicitud vencida.', ['status' => $request->statusValue()], $actorId);

                throw TokenRequestTransitionException::expired();
            }

            // Las abilities las decide el tipo de token, nunca el cliente: no
            // hay forma de pedir una combinación arbitraria desde fuera.
            $abilities = $tokenType->abilities();
            $user = User::query()->active()->findOrFail($ownerUserId);
            $expiresAt = $this->generator->expiresAt($tokenExpiresInDays);
            $created = $this->generator->create($user, trim($tokenName), $abilities, $tokenExpiresInDays);

            /** @var ApiToken $token */
            $token = ApiToken::query()->findOrFail($created->accessToken->id);
            $token->forceFill([
                'description' => 'Token aprobado desde solicitud '.$request->request_uuid,
                'created_by' => $actorId,
            ])->save();

            $this->linker->linkFromRequest($request, $user);

            $request->forceFill([
                'requested_token_name' => trim($tokenName),
                'requested_abilities' => $abilities,
                'token_expires_in_days' => $tokenExpiresInDays,
                'token_type' => $tokenType->value,
                'status' => ApiTokenRequestStatus::Approved,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'approved_at' => now(),
                'personal_access_token_id' => $token->id,
                'token_ciphertext' => $this->vault->encrypt($created->plainTextToken),
                'token_hash' => hash('sha256', $created->plainTextToken),
                'token_last_four' => substr($created->plainTextToken, -4),
                'delivery_status' => ApiTokenRequestDeliveryStatus::Pending,
            ])->save();

            $this->event($request, 'token_type_selected', 'Tipo de token seleccionado.', [
                'token_type' => $tokenType->value,
                'abilities' => $abilities,
            ], $actorId);

            if ($request->requested_token_type !== null && $request->requested_token_type !== $tokenType->value) {
                $this->event($request, 'token_type_changed', 'El administrador aprobó un tipo distinto al solicitado.', [
                    'requested_token_type' => $request->requested_token_type,
                    'token_type' => $tokenType->value,
                ], $actorId);
            }

            $this->event($request, 'approved', 'Solicitud aprobada.', [
                'token_type' => $tokenType->value,
                'abilities' => $abilities,
                'token_expires_in_days' => $tokenExpiresInDays,
                'expires_at' => $expiresAt->toIso8601String(),
            ], $actorId);

            // El valor plano no se registra en ningún sitio: sólo cifrado en la
            // bóveda, de donde se revela una vez para la entrega.
            $this->event($request, 'token_generated', 'Token Sanctum generado sin exponer valor plano.', ['token_type' => $tokenType->value], $actorId);

            NotifyN8nTokenRequestStatus::dispatch($request->id, 'token_request.approved');

            return $request;
        });
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
