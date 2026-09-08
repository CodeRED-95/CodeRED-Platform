<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Resend\Laravel\Facades\Resend;
use Throwable;

class ResendWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $eventId = (string) $request->header('svix-id');
        $timestamp = (string) $request->header('svix-timestamp');
        $signature = (string) $request->header('svix-signature');
        $secret = (string) config('resend.webhook_secret', config('resend.webhook.secret'));

        if ($eventId === '' || $timestamp === '' || $signature === '' || $secret === '') {
            return response()->json(['message' => 'Webhook no válido.'], 400);
        }

        try {
            $event = Resend::webhooks()->verify($payload, [
                'svix-id' => $eventId,
                'svix-timestamp' => $timestamp,
                'svix-signature' => $signature,
            ], $secret);
        } catch (Throwable) {
            Log::warning('resend_webhook_signature_rejected');

            return response()->json(['message' => 'Webhook no válido.'], 400);
        }

        if (! is_array($event) || ! is_string($event['type'] ?? null)) {
            return response()->json(['message' => 'Webhook no válido.'], 400);
        }

        $data = is_array($event['data'] ?? null) ? $event['data'] : [];
        $providerMessageId = is_string($data['email_id'] ?? null) ? $data['email_id'] : null;

        $processed = DB::transaction(function () use ($eventId, $event, $providerMessageId): bool {
            if (EmailWebhookEvent::query()->where('provider_event_id', $eventId)->exists()) {
                return false;
            }

            EmailWebhookEvent::query()->create([
                'provider_event_id' => $eventId,
                'event_type' => $event['type'],
                'provider_message_id' => $providerMessageId,
                'received_at' => now(),
            ]);

            if ($providerMessageId !== null) {
                $this->updateLog((string) $event['type'], $providerMessageId);
            }

            return true;
        });

        return response()->json(['success' => true, 'processed' => $processed]);
    }

    private function updateLog(string $type, string $messageId): void
    {
        $values = match ($type) {
            'email.delivered' => ['status' => EmailLog::DELIVERED, 'delivered_at' => now()],
            'email.bounced' => ['status' => EmailLog::BOUNCED, 'failure_category' => 'bounce'],
            'email.complained' => ['status' => EmailLog::COMPLAINED, 'failure_category' => 'complaint'],
            'email.failed' => ['status' => EmailLog::FAILED, 'failure_category' => 'provider_failed', 'failed_at' => now()],
            default => [],
        };

        if ($values !== []) {
            EmailLog::query()->where('provider_message_id', $messageId)->update($values);
        }
    }
}
