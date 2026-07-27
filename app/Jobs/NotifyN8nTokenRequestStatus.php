<?php

namespace App\Jobs;

use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use App\Services\Integrations\N8nTelegramTokenSettings;
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

    public function handle(N8nTelegramTokenSettings $settings): void
    {
        $request = ApiTokenRequest::query()->findOrFail($this->tokenRequestId);
        $url = $settings->webhookUrl();
        if ($url === '') {
            return;
        }
        $payload = ['event' => $this->event, 'event_uuid' => $this->eventUuid, 'request_uuid' => $request->request_uuid, 'telegram_user_id' => $request->telegram_user_id, 'telegram_chat_id' => $request->telegram_chat_id, 'status' => $request->statusValue(), 'occurred_at' => now()->toIso8601String()];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        try {
            $response = Http::timeout(8)->withHeaders($settings->signedHeaders($body))->withBody($body, 'application/json')->post($url);
            ApiTokenRequestEvent::query()->create(['api_token_request_id' => $request->id, 'event' => 'n8n_notified', 'description' => 'Notificación enviada a n8n.', 'metadata' => ['event_uuid' => $this->eventUuid, 'http_status' => $response->status()], 'created_at' => now()]);
            $response->throw();
        } catch (Throwable $e) {
            ApiTokenRequestEvent::query()->create(['api_token_request_id' => $request->id, 'event' => 'n8n_notification_failed', 'description' => 'No se pudo notificar a n8n.', 'metadata' => ['event_uuid' => $this->eventUuid, 'error' => $e->getMessage()], 'created_at' => now()]);
            throw $e;
        }
    }
}
