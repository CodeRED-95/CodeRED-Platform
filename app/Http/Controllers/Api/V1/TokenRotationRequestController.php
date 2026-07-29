<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenRequestType;
use App\Enums\ApiTokenType;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TokenRotationRequestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'requester_phone' => ['nullable', 'string', 'max:80'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $currentToken = $request->user()?->currentAccessToken();
        if (! $currentToken instanceof ApiToken) {
            $currentToken = $currentToken?->id ? ApiToken::query()->find($currentToken->id) : null;
        }

        if (! $currentToken instanceof ApiToken) {
            return $this->fail('No se pudo identificar el token actual.', 401, 'token_not_identified');
        }

        return DB::transaction(function () use ($currentToken, $data, $request): JsonResponse {
            $source = ApiToken::query()->whereKey($currentToken->id)->lockForUpdate()->firstOrFail();

            if ($source->revoked_at !== null) {
                return $this->fail('El token ya fue revocado.', 422, 'token_revoked');
            }

            if ($source->expires_at?->isPast()) {
                return $this->fail('El token ya expiró.', 422, 'token_expired');
            }

            if ($this->wasAlreadyReplaced($source)) {
                return $this->fail('El token ya fue reemplazado por una rotación anterior.', 422, 'token_already_replaced');
            }

            $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
            if ($idempotencyKey !== '') {
                $existing = ApiTokenRequest::query()
                    ->where('request_type', ApiTokenRequestType::Rotation->value)
                    ->where('source_personal_access_token_id', $source->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    $this->event($existing, 'duplicate_request', 'Solicitud de rotación repetida por idempotencia.', $request);

                    return response()->json(['success' => true, 'message' => 'La solicitud de rotación ya existía.', 'data' => $this->resource($existing)]);
                }
            }

            $pending = ApiTokenRequest::query()
                ->where('request_type', ApiTokenRequestType::Rotation->value)
                ->where('source_personal_access_token_id', $source->id)
                ->where('status', ApiTokenRequestStatus::Pending->value)
                ->exists();

            if ($pending) {
                return $this->fail('Ya existe una solicitud de rotación pendiente para este token.', 422, 'rotation_pending_exists');
            }

            $tokenType = $this->tokenTypeFrom($source);
            if ($tokenType === null) {
                return $this->fail('No se pudo determinar el tipo funcional del token actual.', 422, 'token_type_unknown');
            }

            $tokenRequest = ApiTokenRequest::query()->create([
                'request_uuid' => (string) Str::uuid(),
                'request_type' => ApiTokenRequestType::Rotation,
                'requester_name' => $data['requester_name'] ?? $request->user()?->name,
                'requester_email' => $data['requester_email'] ?? $request->user()?->email,
                'requester_phone' => $data['requester_phone'] ?? null,
                'application_name' => $source->name,
                'purpose' => $data['reason'] ?? 'Solicitud de rotación de token.',
                'telegram_user_id' => 'api-token:'.$source->id,
                'telegram_chat_id' => 'api-token:'.$source->id,
                'requested_token_name' => $source->name,
                'requested_token_type' => $tokenType->value,
                'token_type' => $tokenType->value,
                'requested_abilities' => array_values($source->abilities ?? []),
                'requested_expires_in_minutes' => 1,
                'status' => ApiTokenRequestStatus::Pending,
                'requested_ip' => $request->ip(),
                'request_source' => 'api',
                'idempotency_key' => $idempotencyKey === '' ? null : $idempotencyKey,
                'metadata' => ['source_token_id' => $source->id, 'source_expires_at' => $source->expires_at?->toIso8601String()],
                'requested_at' => now(),
                'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
                'source_personal_access_token_id' => $source->id,
            ]);

            $this->event($tokenRequest, 'rotation_requested', 'Solicitud de rotación creada.', $request, [
                'source_token_id' => $source->id,
                'token_type' => $tokenType->value,
                'expires_at' => $source->expires_at?->toIso8601String(),
            ]);

            return response()->json(['success' => true, 'message' => 'Solicitud de rotación registrada y pendiente de aprobación.', 'data' => $this->resource($tokenRequest)], 201);
        });
    }

    private function tokenTypeFrom(ApiToken $token): ?ApiTokenType
    {
        $abilities = array_values($token->abilities ?? []);
        sort($abilities);

        foreach (ApiTokenType::cases() as $type) {
            $canonical = $type->abilities();
            sort($canonical);

            if ($abilities === $canonical) {
                return $type;
            }
        }

        return null;
    }

    private function wasAlreadyReplaced(ApiToken $token): bool
    {
        return ApiTokenRequest::query()
            ->where('request_type', ApiTokenRequestType::Rotation->value)
            ->where('source_personal_access_token_id', $token->id)
            ->whereNotNull('replacement_personal_access_token_id')
            ->exists();
    }

    private function resource(ApiTokenRequest $request): array
    {
        $sourceToken = $request->sourceToken;
        $deliveryStatus = $request->getAttribute('delivery_status');

        return [
            'request_id' => $request->request_uuid,
            'request_uuid' => $request->request_uuid,
            'request_type' => $request->requestTypeValue(),
            'status' => $request->statusValue(),
            'delivery_status' => $request->deliveryStatusValue(),
            'token_type' => $request->token_type,
            'requested_scopes' => $request->requested_abilities,
            'expires_at' => $sourceToken instanceof ApiToken ? $sourceToken->expires_at?->toIso8601String() : null,
            'rotated' => false,
            'delivery_status_label' => $deliveryStatus instanceof ApiTokenRequestDeliveryStatus ? $deliveryStatus->label() : (string) $request->deliveryStatusValue(),
        ];
    }

    private function event(ApiTokenRequest $request, string $event, string $description, Request $httpRequest, array $metadata = []): void
    {
        ApiTokenRequestEvent::query()->create([
            'api_token_request_id' => $request->id,
            'event' => $event,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $httpRequest->ip(),
            'user_agent' => substr((string) $httpRequest->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }

    private function fail(string $message, int $status, string $code): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'error_code' => $code], $status);
    }
}
