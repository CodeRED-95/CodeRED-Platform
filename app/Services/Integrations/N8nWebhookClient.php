<?php

namespace App\Services\Integrations;

use App\Models\ApiTokenRequest;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class N8nWebhookClient
{
    /** @return array<string, mixed> */
    public function tokenRequestCreatedPayload(WebhookDelivery $delivery, ApiTokenRequest $request): array
    {
        $metadata = $request->metadata ?? [];
        $contact = $request->maskedDeliveryContact();

        return [
            'event' => 'token_request.created',
            'event_id' => $delivery->event_id,
            'occurred_at' => $delivery->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'request' => [
                'id' => $request->id,
                'public_uuid' => $request->request_uuid,
                'tracking_code' => $metadata['tracking_code'] ?? $request->request_uuid,
                'requester_name' => $request->requester_name,
                'integration_type' => $metadata['integration_type'] ?? $request->request_source,
                'installation_name' => $request->application_name ?? $request->requested_token_name,
                'delivery_method' => $request->delivery_channel,
                'masked_contact' => $contact['email'] ?? $contact['telegram'] ?? $contact['whatsapp'],
                'status' => $request->statusValue(),
                'admin_url' => route('admin.api-token-requests.index', ['request' => $request->id]),
            ],
        ];
    }

    public function sendTokenRequestCreated(WebhookDelivery $delivery, ApiTokenRequest $request): int
    {
        $url = (string) config('services.n8n.token_request_notifications.webhook_url');
        $secret = (string) config('services.n8n.token_request_notifications.secret');
        $timeout = (int) config('services.n8n.token_request_notifications.timeout', 10);
        $payload = $this->tokenRequestCreatedPayload($delivery, $request);
        $rawJsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$rawJsonPayload, $secret);

        $response = Http::timeout($timeout)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-CodeRED-Event' => 'token_request.created',
                'X-CodeRED-Event-Id' => $delivery->event_id,
                'X-CodeRED-Timestamp' => $timestamp,
                'X-CodeRED-Signature' => $signature,
            ])
            ->withBody($rawJsonPayload, 'application/json')
            ->post($url);

        if (! $response->successful()) {
            throw new RuntimeException('Webhook n8n falló con estado '.$response->status().'.', $response->status());
        }

        return $response->status();
    }
}
