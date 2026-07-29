<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use App\Services\Integrations\N8nTelegramTokenSettings;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class N8nTokenRequestController extends Controller
{
    public function store(Request $request, N8nTelegramTokenSettings $settings): JsonResponse
    {
        if (! $settings->enabled()) {
            return $this->fail('La integración no está activa.', 403, 'integration_disabled');
        }

        $data = $this->validateStore($request, $settings);
        $abilities = $this->abilitiesFrom($data);
        $this->validateAbilities($abilities, $settings);

        $requesterKey = $this->requesterKey($data);
        $rateKey = 'token-request:'.$requesterKey;
        if (RateLimiter::tooManyAttempts($rateKey, (int) $settings->get('max_pending_per_user', 1))) {
            return $this->fail('Demasiadas solicitudes recientes.', 429, 'rate_limited');
        }
        RateLimiter::hit($rateKey, max(60, (int) $settings->get('cooldown_minutes', 5) * 60));

        $recentPending = ApiTokenRequest::query()
            ->where('telegram_user_id', $this->telegramUserId($data))
            ->where('status', ApiTokenRequestStatus::Pending->value)
            ->where('requested_at', '>=', now()->subMinutes((int) $settings->get('approval_timeout_minutes', 1440)))
            ->exists();

        if ($recentPending) {
            return $this->fail('Ya existe una solicitud pendiente reciente para este solicitante.', 422, 'pending_request_exists');
        }

        $tokenRequest = ApiTokenRequest::query()->create([
            'request_uuid' => (string) Str::uuid(),
            'requester_name' => $data['requester_name'] ?? $this->telegramDisplayName($data),
            'requester_email' => $data['requester_email'] ?? null,
            'requester_phone' => $data['requester_phone'] ?? null,
            'application_name' => $data['application_name'] ?? $data['token_name'],
            'purpose' => $data['purpose'] ?? $data['reason'] ?? null,
            'telegram_user_id' => $this->telegramUserId($data),
            'telegram_chat_id' => $this->telegramChatId($data),
            'telegram_username' => $data['telegram_username'] ?? null,
            'telegram_first_name' => $data['telegram_first_name'] ?? null,
            'telegram_last_name' => $data['telegram_last_name'] ?? null,
            'requested_token_name' => trim((string) ($data['token_name'] ?? $data['application_name'])),
            'requested_abilities' => $abilities,
            'requested_expires_in_minutes' => $this->expirationMinutes($data, $settings),
            'status' => ApiTokenRequestStatus::Pending,
            'requested_ip' => $request->ip(),
            'request_source' => (string) ($data['source'] ?? 'n8n'),
            'metadata' => $data['metadata'] ?? [],
            'requested_at' => now(),
            'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
        ]);
        $this->event($tokenRequest, 'created', 'Solicitud creada desde integración n8n.', $request, ['source' => $tokenRequest->request_source]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud registrada y pendiente de aprobación.',
            'data' => $this->resource($tokenRequest),
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $request = $this->findRequest($uuid);

        return response()->json(['success' => true, 'data' => $this->resource($request)]);
    }

    public function retrieve(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'telegram_user_id' => ['nullable', 'string'],
            'telegram_chat_id' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($uuid, $data, $request): JsonResponse {
            $tokenRequest = ApiTokenRequest::query()->where('request_uuid', $uuid)->lockForUpdate()->firstOrFail();

            if (($data['telegram_user_id'] ?? null) !== null && $tokenRequest->telegram_user_id !== (string) $data['telegram_user_id']) {
                return $this->fail('La solicitud no pertenece al usuario indicado.', 403, 'request_owner_mismatch');
            }
            if (($data['telegram_chat_id'] ?? null) !== null && $tokenRequest->telegram_chat_id !== (string) $data['telegram_chat_id']) {
                return $this->fail('La solicitud no pertenece al chat indicado.', 403, 'request_owner_mismatch');
            }

            $tokenRequest->increment('delivery_attempts');
            $status = $tokenRequest->statusValue();
            if ($status !== ApiTokenRequestStatus::Approved->value) {
                return $this->fail($this->retrieveBlockedMessage($status), 422, 'token_not_retrievable');
            }
            if ($tokenRequest->deliveryStatusValue() === ApiTokenRequestDeliveryStatus::Delivered->value) {
                return $this->fail('El token ya fue entregado.', 409, 'token_already_delivered');
            }
            if ($tokenRequest->result_retrieved_at !== null || blank($tokenRequest->encrypted_plain_text_token)) {
                return $this->fail('El token ya fue recuperado anteriormente.', 409, 'token_already_retrieved');
            }

            $plain = Crypt::decryptString($tokenRequest->encrypted_plain_text_token);
            $expiresAt = $tokenRequest->personal_access_token_id ? ApiToken::query()->find($tokenRequest->personal_access_token_id)?->expires_at?->toIso8601String() : null;
            $tokenRequest->forceFill([
                'encrypted_plain_text_token' => null,
                'result_retrieved_at' => now(),
                'delivery_status' => ApiTokenRequestDeliveryStatus::Retrieved,
            ])->save();
            $this->event($tokenRequest, 'token_retrieved', 'Token recuperado una sola vez.', $request);

            return response()->json([
                'success' => true,
                'message' => 'Token recuperado correctamente.',
                'data' => [
                    'request_id' => $tokenRequest->request_uuid,
                    'request_uuid' => $tokenRequest->request_uuid,
                    'token' => $plain,
                    'token_type' => 'Bearer',
                    'abilities' => $tokenRequest->requested_abilities,
                    'expires_at' => $expiresAt,
                ],
            ]);
        });
    }

    public function delivery(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'delivered' => ['nullable', 'boolean'],
            'delivery_channel' => ['nullable', 'string', 'max:40'],
            'delivered_to' => ['nullable', 'string', 'max:255'],
            'delivery_metadata' => ['nullable', 'array'],
            'telegram_message_id' => ['nullable', 'string', 'max:120'],
        ]);

        return DB::transaction(function () use ($uuid, $data, $request): JsonResponse {
            $tokenRequest = ApiTokenRequest::query()->where('request_uuid', $uuid)->lockForUpdate()->firstOrFail();

            if ($tokenRequest->deliveryStatusValue() === ApiTokenRequestDeliveryStatus::Delivered->value) {
                return response()->json(['success' => true, 'message' => 'La entrega ya estaba confirmada.', 'data' => $this->resource($tokenRequest)]);
            }
            if (! in_array($tokenRequest->statusValue(), [ApiTokenRequestStatus::Approved->value], true)) {
                return $this->fail('Solo una solicitud aprobada puede marcarse como entregada.', 422, 'invalid_delivery_state');
            }

            $delivered = (bool) ($data['delivered'] ?? true);
            $tokenRequest->forceFill([
                'delivery_status' => $delivered ? ApiTokenRequestDeliveryStatus::Delivered : ApiTokenRequestDeliveryStatus::Failed,
                'delivered_at' => $delivered ? now() : null,
                'delivery_attempts' => $tokenRequest->delivery_attempts + 1,
                'delivery_channel' => $data['delivery_channel'] ?? 'manual',
                'delivered_to' => $data['delivered_to'] ?? null,
                'delivery_metadata' => $data['delivery_metadata'] ?? [],
                'delivery_reference' => $data['telegram_message_id'] ?? null,
                'encrypted_plain_text_token' => null,
            ])->save();
            $this->event($tokenRequest, $delivered ? 'delivery_confirmed' : 'delivery_failed', 'n8n confirmó el estado de entrega.', $request, ['delivery_channel' => $tokenRequest->delivery_channel]);

            return response()->json(['success' => true, 'message' => 'Entrega actualizada correctamente.', 'data' => $this->resource($tokenRequest)]);
        });
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['cancellation_reason' => ['nullable', 'string', 'max:1000']]);

        return DB::transaction(function () use ($uuid, $data, $request): JsonResponse {
            $tokenRequest = ApiTokenRequest::query()->where('request_uuid', $uuid)->lockForUpdate()->firstOrFail();
            $status = $tokenRequest->statusValue();

            if ($status === ApiTokenRequestStatus::Cancelled->value) {
                return response()->json(['success' => true, 'message' => 'La solicitud ya estaba cancelada.', 'data' => $this->resource($tokenRequest)]);
            }
            if ($status !== ApiTokenRequestStatus::Pending->value) {
                return $this->fail('Solo una solicitud pendiente puede cancelarse.', 409, 'invalid_cancellation_state');
            }

            $tokenRequest->forceFill([
                'status' => ApiTokenRequestStatus::Cancelled,
                'cancelled_at' => now(),
                'reviewed_at' => now(),
                'cancellation_reason' => $data['cancellation_reason'] ?? null,
                'encrypted_plain_text_token' => null,
                'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
            ])->save();
            $this->event($tokenRequest, 'cancelled', 'Solicitud cancelada desde integración n8n.', $request);

            return response()->json(['success' => true, 'message' => 'Solicitud cancelada correctamente.', 'data' => $this->resource($tokenRequest)]);
        });
    }

    private function validateStore(Request $request, N8nTelegramTokenSettings $settings): array
    {
        return $request->validate([
            'requester_name' => ['nullable', 'required_without:telegram_user_id', 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'requester_phone' => ['nullable', 'string', 'max:80'],
            'application_name' => ['nullable', 'required_without:token_name', 'string', 'min:3', 'max:100'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'requested_scopes' => ['nullable', 'array', 'min:1'],
            'requested_scopes.*' => ['required', 'string', Rule::in($settings->allowedAbilities()), 'not_in:*'],
            'permissions' => ['nullable', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', Rule::in($settings->allowedAbilities()), 'not_in:*'],
            'source' => ['nullable', 'string', 'max:80'],
            'metadata' => ['nullable', 'array'],
            'expiration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'telegram_user_id' => ['nullable', 'string', 'max:80'],
            'telegram_chat_id' => ['nullable', 'string', 'max:80'],
            'telegram_username' => ['nullable', 'string', 'max:120'],
            'telegram_first_name' => ['nullable', 'string', 'max:120'],
            'telegram_last_name' => ['nullable', 'string', 'max:120'],
            'token_name' => ['nullable', 'string', 'min:3', 'max:100'],
            'abilities' => ['nullable', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', Rule::in($settings->allowedAbilities()), 'not_in:*'],
            'expires_in_minutes' => ['nullable', 'integer', 'min:1', 'max:'.(int) $settings->get('max_expires_in_minutes', 1440)],
        ]);
    }

    private function abilitiesFrom(array $data): array
    {
        $abilities = $data['requested_scopes'] ?? $data['permissions'] ?? $data['abilities'] ?? [];

        return array_values(array_unique(array_map('strval', $abilities)));
    }

    private function validateAbilities(array $abilities, N8nTelegramTokenSettings $settings): void
    {
        validator(['abilities' => $abilities], ['abilities' => ['required', 'array', 'min:1'], 'abilities.*' => ['required', 'string', Rule::in($settings->allowedAbilities()), 'not_in:*']])->validate();
        foreach ($abilities as $ability) {
            if ($ability === '*' || Str::startsWith($ability, ['admin:', 'users:', 'api-token-requests.'])) {
                abort(response()->json(['success' => false, 'message' => 'Permiso no permitido.', 'error_code' => 'ability_not_allowed'], 422));
            }
        }
    }

    private function expirationMinutes(array $data, N8nTelegramTokenSettings $settings): int
    {
        if (isset($data['expiration_days'])) {
            return (int) $data['expiration_days'] * 1440;
        }

        return (int) ($data['expires_in_minutes'] ?? $settings->get('default_expires_in_minutes', 60));
    }

    private function requesterKey(array $data): string
    {
        return hash('sha256', implode('|', [$this->telegramUserId($data), $this->telegramChatId($data), $data['requester_email'] ?? '', $data['requester_phone'] ?? '']));
    }

    private function telegramUserId(array $data): string
    {
        if (isset($data['telegram_user_id']) && $data['telegram_user_id'] !== '') {
            return (string) $data['telegram_user_id'];
        }

        return 'n8n:'.hash('sha256', (string) ($data['requester_email'] ?? $data['requester_phone'] ?? $data['requester_name'] ?? Str::uuid()));
    }

    private function telegramChatId(array $data): string
    {
        return (string) ($data['telegram_chat_id'] ?? $this->telegramUserId($data));
    }

    private function telegramDisplayName(array $data): ?string
    {
        return trim(implode(' ', array_filter([(string) ($data['telegram_first_name'] ?? ''), (string) ($data['telegram_last_name'] ?? '')]))) ?: ($data['telegram_username'] ?? null);
    }

    private function findRequest(string $uuid): ApiTokenRequest
    {
        return ApiTokenRequest::query()->where('request_uuid', $uuid)->firstOrFail();
    }

    private function effectiveStatus(ApiTokenRequest $request): string
    {
        if ($request->deliveryStatusValue() === ApiTokenRequestDeliveryStatus::Delivered->value) {
            return 'delivered';
        }

        return $request->statusValue();
    }

    private function resource(ApiTokenRequest $request): array
    {
        $token = $request->personal_access_token_id ? ApiToken::query()->find($request->personal_access_token_id) : null;

        return [
            'request_id' => $request->request_uuid,
            'request_uuid' => $request->request_uuid,
            'public_id' => $request->request_uuid,
            'status' => $this->effectiveStatus($request),
            'request_status' => $request->statusValue(),
            'delivery_status' => $request->deliveryStatusValue(),
            'requester_name' => $request->requester_name,
            'requester_email' => $request->requester_email,
            'requester_phone' => $request->requester_phone,
            'application_name' => $request->application_name,
            'purpose' => $request->purpose,
            'requested_scopes' => $request->requested_abilities,
            'created_at' => $request->created_at?->toIso8601String(),
            'requested_at' => $request->requestedAt()?->toIso8601String(),
            'approved_at' => $this->isoDate($request->getAttribute('approved_at')),
            'rejected_at' => $this->isoDate($request->getAttribute('rejected_at')),
            'cancelled_at' => $this->isoDate($request->getAttribute('cancelled_at')),
            'delivered_at' => $this->isoDate($request->getAttribute('delivered_at')),
            'delivery_channel' => $request->delivery_channel,
            'delivered_to' => $request->delivered_to,
            'rejection_reason' => $request->statusValue() === ApiTokenRequestStatus::Rejected->value ? $request->rejection_reason : null,
            'cancellation_reason' => $request->statusValue() === ApiTokenRequestStatus::Cancelled->value ? $request->cancellation_reason : null,
            'expires_at' => $token?->expires_at?->toIso8601String(),
            'metadata' => $request->metadata ?? [],
        ];
    }

    private function isoDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return Carbon::parse((string) $value)->toIso8601String();
    }

    private function retrieveBlockedMessage(string $status): string
    {
        return match ($status) {
            ApiTokenRequestStatus::Pending->value => 'La solicitud todavía está pendiente de aprobación.',
            ApiTokenRequestStatus::Rejected->value => 'La solicitud fue rechazada.',
            ApiTokenRequestStatus::Cancelled->value => 'La solicitud fue cancelada.',
            ApiTokenRequestStatus::Expired->value => 'La solicitud expiró.',
            default => 'El token no puede recuperarse en el estado actual.',
        };
    }

    private function fail(string $message, int $status, string $code = 'request_failed'): JsonResponse
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
