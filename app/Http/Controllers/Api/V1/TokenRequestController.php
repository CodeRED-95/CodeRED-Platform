<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenRequestType;
use App\Jobs\NotifyN8nTokenRequestStatus;
use App\Models\ApiTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TokenRequestController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->merge([
            'delivery_destination' => $this->normalizeDestination(
                $request->input('delivery_channel'),
                $request->input('delivery_destination')
            ),
        ]);

        $data = $request->validate([
            'requester_name' => ['required', 'string', 'max:255'],
            'delivery_channel' => ['required', 'string', Rule::in(['whatsapp', 'telegram', 'email'])],
            'delivery_destination' => ['required', 'string', 'max:255'],
            'instance_name' => ['required', 'string', 'max:150'],
            'source' => ['required', 'string', 'max:80'],
            'requested_scopes' => ['required', 'array', 'size:1'],
            'requested_scopes.0' => ['required', 'string', Rule::in(['agencies:read'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $requestId = (string) Str::uuid();
        $fingerprint = hash_hmac('sha256', implode('|', [$data['delivery_channel'], $data['delivery_destination'], $data['instance_name']]), config('app.key'));
        $tokenRequest = ApiTokenRequest::query()->create([
            'request_uuid' => $requestId,
            'request_type' => ApiTokenRequestType::Issuance,
            'requester_name' => trim((string) $data['requester_name']),
            'requester_phone' => $data['delivery_channel'] === 'whatsapp' ? $data['delivery_destination'] : null,
            'requester_email' => $data['delivery_channel'] === 'email' ? $data['delivery_destination'] : null,
            'telegram_user_id' => 'public:'.hash('sha256', $fingerprint.':telegram-user'),
            'telegram_chat_id' => 'public:'.hash('sha256', $fingerprint.':telegram-chat'),
            'application_name' => trim((string) $data['instance_name']),
            'purpose' => $data['notes'] ?? 'Solicitud desde extension Chrome.',
            'requested_token_name' => trim((string) $data['instance_name']),
            'requested_token_type' => 'agencies',
            'requested_abilities' => ['agencies:read'],
            'requested_expires_in_minutes' => 60,
            'token_expires_in_days' => 30,
            'status' => ApiTokenRequestStatus::Pending,
            'request_source' => trim((string) $data['source']),
            'metadata' => [
                'delivery_channel' => $data['delivery_channel'],
                'delivery_destination' => $data['delivery_destination'],
                'delivery_destination_encrypted' => Crypt::encryptString($data['delivery_destination']),
                'requested_scopes' => ['agencies:read'],
            ],
            'requested_at' => now(),
            'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
        ]);

        NotifyN8nTokenRequestStatus::dispatch($tokenRequest->id, 'token_request.created');

        return response()->json([
            'success' => true,
            'data' => [
                'request_id' => $tokenRequest->request_uuid,
                'status' => 'pending',
                'message' => 'Solicitud enviada correctamente.',
            ],
        ], 201);
    }

    private function normalizeDestination(mixed $channel, mixed $value): string
    {
        $trimmed = is_string($value) ? trim($value) : '';
        if ($trimmed === '') {
            return '';
        }

        return match ($channel) {
            'whatsapp' => preg_replace('/[\s()-]/', '', $trimmed) ?? '',
            'telegram' => '@'.ltrim(str_replace('https://t.me/', '', $trimmed), '@'),
            'email' => strtolower($trimmed),
            default => $trimmed,
        };
    }
}
