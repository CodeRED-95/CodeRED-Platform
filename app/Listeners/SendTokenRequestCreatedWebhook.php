<?php

namespace App\Listeners;

use App\Events\TokenRequestCreated;
use App\Models\ApiTokenRequest;
use App\Models\WebhookDelivery;
use App\Services\Integrations\N8nWebhookClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendTokenRequestCreatedWebhook implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    public int $timeout = 15;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60, 180, 300];
    }

    public function shouldQueue(TokenRequestCreated $event): bool
    {
        return ! $this->shouldSkipExternalDelivery() && $this->enabled();
    }

    public function handle(TokenRequestCreated $event): void
    {
        if ($this->shouldSkipExternalDelivery()) {
            return;
        }

        if (! $this->enabled()) {
            return;
        }

        $url = (string) config('services.n8n.token_request_notifications.webhook_url');
        $delivery = WebhookDelivery::query()->firstOrCreate(
            ['event_id' => $event->eventId],
            [
                'event_type' => 'token_request.created',
                'aggregate_type' => ApiTokenRequest::class,
                'aggregate_id' => $event->tokenRequest->id,
                'destination' => $url,
                'status' => 'pending',
            ]
        );

        if ($delivery->delivered_at !== null) {
            return;
        }

        $delivery->forceFill([
            'attempts' => $delivery->attempts + 1,
            'status' => 'sending',
            'failed_at' => null,
            'last_error' => null,
        ])->save();

        try {
            $status = app(N8nWebhookClient::class)->sendTokenRequestCreated($delivery, $event->tokenRequest->fresh() ?? $event->tokenRequest);
            $delivery->forceFill([
                'status' => 'delivered',
                'last_status_code' => $status,
                'delivered_at' => now(),
                'failed_at' => null,
                'last_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'last_status_code' => $exception->getCode() >= 100 && $exception->getCode() <= 599 ? $exception->getCode() : null,
                'failed_at' => now(),
                'last_error' => $this->safeError($exception),
            ])->save();

            Log::warning('[CodeRED] Falló notificación token_request.created a n8n', [
                'event_id' => $delivery->event_id,
                'token_request_id' => $delivery->aggregate_id,
                'status_code' => $delivery->last_status_code,
            ]);

            throw $exception;
        }
    }

    private function enabled(): bool
    {
        return (bool) config('services.n8n.token_request_notifications.enabled', false)
            && filled(config('services.n8n.token_request_notifications.webhook_url'))
            && filled(config('services.n8n.token_request_notifications.secret'));
    }

    private function shouldSkipExternalDelivery(): bool
    {
        return (bool) config('app.testing', false)
            || app()->runningUnitTests()
            || app()->environment('testing')
            || config('app.env') === 'testing'
            || getenv('APP_ENV') === 'testing'
            || ($_ENV['APP_ENV'] ?? null) === 'testing';
    }

    private function safeError(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        if ($message === '') {
            return 'Webhook n8n falló sin detalle.';
        }

        return mb_substr($message, 0, 500);
    }
}
