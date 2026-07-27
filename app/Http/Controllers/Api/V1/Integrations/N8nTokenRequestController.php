<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use App\Services\Integrations\N8nTelegramTokenSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class N8nTokenRequestController extends Controller
{
    public function store(Request $request, N8nTelegramTokenSettings $settings): JsonResponse
    {
        if (! $settings->enabled()) {
            return $this->fail('La integración no está activa.', 403);
        }

        $allowed = $settings->allowedAbilities();
        $data = $request->validate([
            'telegram_user_id' => ['required', 'string', 'max:80'],
            'telegram_chat_id' => ['required', 'string', 'max:80'],
            'telegram_username' => ['nullable', 'string', 'max:120'],
            'telegram_first_name' => ['nullable', 'string', 'max:120'],
            'telegram_last_name' => ['nullable', 'string', 'max:120'],
            'token_name' => ['required', 'string', 'min:3', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', Rule::in($allowed), 'not_in:*'],
            'expires_in_minutes' => ['nullable', 'integer', 'min:1', 'max:'.(int) $settings->get('max_expires_in_minutes', 1440)],
        ]);

        if ($settings->authorizedUsers() !== [] && ! in_array((string) $data['telegram_user_id'], $settings->authorizedUsers(), true)) {
            return $this->fail('Usuario de Telegram no autorizado.', 403);
        }
        if ($settings->authorizedChats() !== [] && ! in_array((string) $data['telegram_chat_id'], $settings->authorizedChats(), true)) {
            return $this->fail('Chat de Telegram no autorizado.', 403);
        }
        foreach ($data['abilities'] as $ability) {
            if ($ability === '*' || Str::startsWith($ability, ['admin:', 'users:', 'api-token-requests.'])) {
                return $this->fail('Permiso no permitido.', 422);
            }
        }

        $rateKey = 'token-request:'.$data['telegram_user_id'].':'.$data['telegram_chat_id'];
        if (RateLimiter::tooManyAttempts($rateKey, (int) $settings->get('max_pending_per_user', 1))) {
            return $this->fail('Demasiadas solicitudes recientes.', 429);
        }
        RateLimiter::hit($rateKey, max(60, (int) $settings->get('cooldown_minutes', 5) * 60));

        $recentPending = ApiTokenRequest::query()->where('telegram_user_id', $data['telegram_user_id'])->where('status', ApiTokenRequestStatus::Pending->value)->where('requested_at', '>=', now()->subMinutes((int) $settings->get('approval_timeout_minutes', 1440)))->exists();
        if ($recentPending) {
            return $this->fail('Ya existe una solicitud pendiente reciente para este usuario.', 422);
        }

        $tokenRequest = ApiTokenRequest::query()->create([
            'request_uuid' => (string) Str::uuid(),
            'telegram_user_id' => (string) $data['telegram_user_id'],
            'telegram_chat_id' => (string) $data['telegram_chat_id'],
            'telegram_username' => $data['telegram_username'] ?? null,
            'telegram_first_name' => $data['telegram_first_name'] ?? null,
            'telegram_last_name' => $data['telegram_last_name'] ?? null,
            'requested_token_name' => trim($data['token_name']),
            'requested_abilities' => array_values(array_unique($data['abilities'])),
            'requested_expires_in_minutes' => (int) ($data['expires_in_minutes'] ?? $settings->get('default_expires_in_minutes', 60)),
            'status' => ApiTokenRequestStatus::Pending,
            'requested_ip' => $request->ip(),
            'request_source' => 'telegram_n8n',
            'requested_at' => now(),
            'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
        ]);
        $this->event($tokenRequest, 'created', 'Solicitud creada desde n8n.', $request);

        return response()->json(['success' => true, 'message' => 'Solicitud registrada y pendiente de aprobación.', 'data' => ['request_uuid' => $tokenRequest->request_uuid, 'status' => $tokenRequest->statusValue(), 'requested_at' => $tokenRequest->requestedAt()?->toIso8601String()]], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $r = ApiTokenRequest::query()->where('request_uuid', $uuid)->firstOrFail();
        $token = $r->personal_access_token_id ? ApiToken::query()->find($r->personal_access_token_id) : null;

        return response()->json(['success' => true, 'data' => ['request_uuid' => $r->request_uuid, 'status' => $r->statusValue(), 'delivery_status' => $r->deliveryStatusValue(), 'requested_at' => $r->requestedAt()?->toIso8601String(), 'reviewed_at' => $r->reviewedAt()?->toIso8601String(), 'expires_at' => $token?->expires_at?->toIso8601String(), 'rejection_reason' => $r->statusValue() === ApiTokenRequestStatus::Rejected->value ? $r->rejection_reason : null]]);
    }

    public function retrieve(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['telegram_user_id' => ['required', 'string'], 'telegram_chat_id' => ['required', 'string']]);
        $r = ApiTokenRequest::query()->where('request_uuid', $uuid)->lockForUpdate()->firstOrFail();
        if ($r->telegram_user_id !== (string) $data['telegram_user_id'] || $r->telegram_chat_id !== (string) $data['telegram_chat_id']) {
            return $this->fail('La solicitud no pertenece al usuario o chat indicado.', 403);
        }

        $r->increment('delivery_attempts');
        if ($r->statusValue() !== ApiTokenRequestStatus::Approved->value) {
            return $this->fail('La solicitud todavía no está aprobada.', 422);
        }
        if ($r->result_retrieved_at !== null || blank($r->encrypted_plain_text_token)) {
            return $this->fail('El token ya fue recuperado anteriormente.', 409);
        }

        $plain = Crypt::decryptString($r->encrypted_plain_text_token);
        $r->forceFill(['encrypted_plain_text_token' => null, 'result_retrieved_at' => now(), 'delivery_status' => ApiTokenRequestDeliveryStatus::Retrieved])->save();
        $this->event($r, 'token_retrieved', 'Token recuperado una sola vez.', $request);
        $expiresAt = $r->personal_access_token_id ? ApiToken::query()->find($r->personal_access_token_id)?->expires_at?->toIso8601String() : null;

        return response()->json(['success' => true, 'message' => 'Token recuperado correctamente.', 'data' => ['token' => $plain, 'token_type' => 'Bearer', 'abilities' => $r->requested_abilities, 'expires_at' => $expiresAt]]);
    }

    public function delivery(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['delivered' => ['required', 'boolean'], 'telegram_message_id' => ['nullable', 'string', 'max:120']]);
        $r = ApiTokenRequest::query()->where('request_uuid', $uuid)->firstOrFail();
        $r->forceFill(['delivery_status' => $data['delivered'] ? ApiTokenRequestDeliveryStatus::Delivered : ApiTokenRequestDeliveryStatus::Failed, 'delivered_at' => $data['delivered'] ? now() : null, 'delivery_attempts' => $r->delivery_attempts + 1, 'delivery_reference' => $data['telegram_message_id'] ?? null])->save();
        $this->event($r, $data['delivered'] ? 'delivery_confirmed' : 'delivery_failed', 'n8n confirmó el estado de entrega.', $request);

        return response()->json(['success' => true, 'message' => 'Entrega actualizada correctamente.']);
    }

    private function fail(string $m, int $s): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $m], $s);
    }

    private function event(ApiTokenRequest $r, string $event, string $description, Request $request): void
    {
        ApiTokenRequestEvent::query()->create(['api_token_request_id' => $r->id, 'event' => $event, 'description' => $description, 'metadata' => [], 'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000), 'created_at' => now()]);
    }
}
