<?php

namespace App\Jobs;

use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use App\Models\Integration;
use App\Services\Integrations\IntegrationProtocolService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class NotifyN8nTokenRequestStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 10;

    public function __construct(public int $tokenRequestId, public string $event, public string $eventUuid = '')
    {
        $this->eventUuid = $eventUuid ?: (string) Str::uuid();
    }

    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function handle(IntegrationProtocolService $protocol): void
    {
        $request = ApiTokenRequest::query()->findOrFail($this->tokenRequestId);
        $service = match ($this->event) {
            'token_request.approved' => 'token.request.approved',
            'token_request.rejected' => 'token.request.rejected',
            'token_request.cancelled' => 'token.request.cancelled',
            'token_request.expired' => 'token.request.expired',
            'token_request.revoked' => 'token.request.revoked',
            default => 'token.request.status',
        };
        $integration = Integration::query()->where('provider', 'n8n')->whereNotNull('last_seen_at')->latest('last_seen_at')->first();
        $capability = $integration?->capabilities()->where('service', $service)->where('enabled', true)->first();
        if (! $integration || ! $capability || blank($integration->instance_url)) {
            ApiTokenRequestEvent::query()->create(['api_token_request_id' => $request->id, 'event' => 'n8n_notification_skipped', 'description' => 'No existe una capacidad discovery para '.$service.'.', 'metadata' => ['service' => $service], 'created_at' => now()]);

            return;
        }

        $payload = ['event' => $this->event, 'event_uuid' => $this->eventUuid, 'service' => $service, 'request_uuid' => $request->request_uuid, 'telegram_user_id' => $request->telegram_user_id, 'telegram_chat_id' => $request->telegram_chat_id, 'status' => $request->statusValue(), 'occurred_at' => now()->toIso8601String()];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        try {
            $method = (string) $capability->getAttribute('method');
            $path = (string) $capability->getAttribute('path');
            $response = Http::timeout(8)->withHeaders($protocol->signedHeaders($integration, $method, $path, $body))->withBody($body, 'application/json')->send($method, rtrim((string) $integration->instance_url, '/').$path);
            ApiTokenRequestEvent::query()->create(['api_token_request_id' => $request->id, 'event' => 'n8n_notified', 'description' => 'Notificación enviada mediante Capability Registry.', 'metadata' => ['event_uuid' => $this->eventUuid, 'service' => $service, 'integration_uuid' => $integration->integration_uuid, 'http_status' => $response->status()], 'created_at' => now()]);
            $response->throw();
        } catch (Throwable $e) {
            ApiTokenRequestEvent::query()->create(['api_token_request_id' => $request->id, 'event' => 'n8n_notification_failed', 'description' => 'No se pudo notificar a n8n.', 'metadata' => ['event_uuid' => $this->eventUuid, 'service' => $service, 'error_class' => $e::class, 'error_code' => $e->getCode() ?: null], 'created_at' => now()]);
            throw $e;
        }
    }
}
