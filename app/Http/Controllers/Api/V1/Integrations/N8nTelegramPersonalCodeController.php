<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenRequestType;
use App\Enums\ApiTokenType;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use App\Models\Integration;
use App\Models\User;
use App\Services\ApiTokens\TelegramRequesterLinker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class N8nTelegramPersonalCodeController extends Controller
{
    public function show(Request $request, TelegramRequesterLinker $linker): JsonResponse
    {
        $data = $request->validate([
            'telegram_user_id' => ['required', 'string', 'max:80'],
            'telegram_chat_id' => ['required', 'string', 'max:80'],
        ]);

        $integration = $this->integration($request);
        if (! $this->integrationCanAct($integration)) {
            return $this->fail('La integración no está conectada.', 403, 'integration_not_connected');
        }

        $rateKey = 'telegram-personal-code:'.$integration->id.':'.hash('sha256', $data['telegram_user_id'].'|'.$data['telegram_chat_id']);
        if (RateLimiter::tooManyAttempts($rateKey, 30)) {
            return $this->fail('Demasiadas consultas recientes.', 429, 'rate_limited');
        }
        RateLimiter::hit($rateKey, 60);

        $user = User::query()
            ->where('telegram_user_id', (string) $data['telegram_user_id'])
            ->where('telegram_chat_id', (string) $data['telegram_chat_id'])
            ->first();

        if (! $user instanceof User) {
            return $this->fail('No encontramos un perfil vinculado a este usuario de Telegram. Primero debes realizar una solicitud de token.', 404, 'telegram_profile_not_linked');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'person_code' => $linker->ensurePublicCode($user),
                'display_name' => $user->name,
            ],
        ]);
    }

    public function rotation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'person_code' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'telegram_user_id' => ['required', 'string', 'max:80'],
            'telegram_chat_id' => ['required', 'string', 'max:80'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:180'],
            'source' => ['nullable', 'string', 'max:80'],
        ]);

        $integration = $this->integration($request);
        if (! $this->integrationCanAct($integration)) {
            return $this->fail('La integración no está conectada.', 403, 'integration_not_connected');
        }

        $existing = ApiTokenRequest::query()
            ->where('idempotency_key', (string) $data['idempotency_key'])
            ->first();

        if ($existing instanceof ApiTokenRequest) {
            $metadata = $existing->metadata ?? [];
            if (
                $existing->requestTypeValue() !== ApiTokenRequestType::Rotation->value
                || $existing->telegram_user_id !== (string) $data['telegram_user_id']
                || $existing->telegram_chat_id !== (string) $data['telegram_chat_id']
                || ($metadata['person_code'] ?? null) !== (string) $data['person_code']
            ) {
                return $this->fail('La llave de idempotencia ya fue utilizada para otra solicitud.', 409, 'idempotency_key_conflict');
            }

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de rotación registrada previamente.',
                'data' => $this->rotationResource($existing, (string) $data['person_code']),
            ]);
        }

        $user = User::query()
            ->where('public_code', (string) $data['person_code'])
            ->where('telegram_user_id', (string) $data['telegram_user_id'])
            ->where('telegram_chat_id', (string) $data['telegram_chat_id'])
            ->first();

        if (! $user instanceof User) {
            return $this->fail('El código personal no corresponde al usuario de Telegram indicado.', 403, 'person_code_mismatch');
        }

        return DB::transaction(function () use ($data, $integration, $user, $request): JsonResponse {
            $tokens = ApiToken::query()
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->id)
                ->whereNull('revoked_at')
                ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->lockForUpdate()
                ->get();

            if ($tokens->isEmpty()) {
                return $this->fail('No encontramos un token activo elegible para rotar.', 422, 'active_token_not_found');
            }

            if ($tokens->count() > 1) {
                return $this->fail('Existen varios tokens activos. Selecciona el token a rotar desde el panel administrativo.', 409, 'multiple_active_tokens');
            }

            /** @var ApiToken $source */
            $source = $tokens->first();

            $pending = ApiTokenRequest::query()
                ->where('request_type', ApiTokenRequestType::Rotation->value)
                ->where('source_personal_access_token_id', $source->id)
                ->where('status', ApiTokenRequestStatus::Pending->value)
                ->lockForUpdate()
                ->first();

            if ($pending instanceof ApiTokenRequest) {
                return $this->fail('Ya existe una solicitud de rotación pendiente para este token.', 422, 'rotation_pending_exists');
            }

            $tokenType = $this->tokenTypeFor($source);
            $tokenRequest = ApiTokenRequest::query()->create([
                'request_uuid' => (string) Str::uuid(),
                'request_type' => ApiTokenRequestType::Rotation,
                'requester_name' => $user->name,
                'application_name' => $source->name,
                'purpose' => (string) $data['reason'],
                'telegram_user_id' => (string) $data['telegram_user_id'],
                'telegram_chat_id' => (string) $data['telegram_chat_id'],
                'requested_token_name' => $source->name,
                'requested_token_type' => $tokenType,
                'token_type' => $tokenType,
                'requested_abilities' => array_values($source->abilities ?? []),
                'requested_expires_in_minutes' => $source->expires_at ? (int) max(1, ceil(now()->diffInMinutes($source->expires_at, false))) : null,
                'status' => ApiTokenRequestStatus::Pending,
                'requested_ip' => $request->ip(),
                'request_source' => (string) ($data['source'] ?? 'telegram'),
                'idempotency_key' => (string) $data['idempotency_key'],
                'metadata' => [
                    'person_code' => (string) $data['person_code'],
                    'integration_uuid' => $integration->integration_uuid,
                    'source_token_id' => $source->id,
                ],
                'requested_at' => now(),
                'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
                'source_personal_access_token_id' => $source->id,
            ]);

            $this->event($tokenRequest, 'rotation_requested', 'Solicitud de rotación creada desde Telegram.', $request, [
                'integration_uuid' => $integration->integration_uuid,
                'source_token_id' => $source->id,
                'token_type' => $tokenType,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de rotación registrada.',
                'data' => $this->rotationResource($tokenRequest, (string) $data['person_code']),
            ], 201);
        });
    }

    private function tokenTypeFor(ApiToken $token): ?string
    {
        $abilities = array_values($token->abilities ?? []);
        sort($abilities);

        foreach (ApiTokenType::cases() as $type) {
            $expected = $type->abilities();
            sort($expected);
            if ($abilities === $expected) {
                return $type->value;
            }
        }

        return null;
    }

    private function integration(Request $request): Integration
    {
        $integration = $request->attributes->get('integration');
        abort_unless($integration instanceof Integration, 401, 'Integración no reconocida.');

        return $integration;
    }

    private function integrationCanAct(Integration $integration): bool
    {
        return ! $integration->isRevoked() && in_array($integration->connectionStatus(), ['connected', 'degraded'], true);
    }

    private function rotationResource(ApiTokenRequest $request, string $personCode): array
    {
        return [
            'request_id' => $request->request_uuid,
            'request_uuid' => $request->request_uuid,
            'request_type' => $request->requestTypeValue(),
            'status' => $request->statusValue(),
            'person_code' => $personCode,
            'message' => 'Solicitud de rotación registrada.',
        ];
    }

    private function fail(string $message, int $status, string $code): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'error_code' => $code], $status);
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
}
