<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenRequestType;
use App\Models\ApiTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TokenRequestController
{
    public function __invoke(Request $request): JsonResponse
    {
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
        $tokenRequest = ApiTokenRequest::query()->create([
            'request_uuid' => $requestId,
            'request_type' => ApiTokenRequestType::Issuance,
            'requester_name' => trim((string) $data['requester_name']),
            'requester_phone' => $data['delivery_channel'] === 'whatsapp' ? $data['delivery_destination'] : null,
            'requester_email' => $data['delivery_channel'] === 'email' ? $data['delivery_destination'] : null,
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
                'requested_scopes' => ['agencies:read'],
            ],
            'requested_at' => now(),
            'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'request_id' => $tokenRequest->request_uuid,
                'status' => 'pending',
                'message' => 'Solicitud enviada correctamente.',
            ],
        ], 201);
    }
}
