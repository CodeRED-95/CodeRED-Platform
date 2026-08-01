<?php

namespace App\Actions\ApiTokenRequests;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use Illuminate\Support\Facades\DB;

class MarkTokenRequestAsDeliveredAction
{
    public function execute(ApiTokenRequest $tokenRequest, ?int $deliveredBy, string $channel = 'manual', ?string $reference = null, array $metadata = []): ApiTokenRequest
    {
        return DB::transaction(function () use ($tokenRequest, $deliveredBy, $channel, $reference, $metadata): ApiTokenRequest {
            $request = ApiTokenRequest::query()->whereKey($tokenRequest->id)->lockForUpdate()->firstOrFail();

            if ($request->isDelivered()) {
                abort(422, 'La solicitud ya fue marcada como entregada.');
            }

            if ($request->statusValue() !== ApiTokenRequestStatus::Approved->value) {
                abort(422, 'Solo una solicitud aprobada puede marcarse como entregada.');
            }

            $masked = $request->maskedDeliveryContact();

            $request->forceFill([
                'delivery_status' => ApiTokenRequestDeliveryStatus::Delivered,
                'delivered_at' => now(),
                'delivered_by' => $deliveredBy,
                'delivery_attempts' => $request->delivery_attempts + 1,
                'delivery_channel' => $channel,
                'delivery_reference' => $reference,
                'delivery_metadata' => $metadata,
                'delivered_to' => $this->firstMaskedValue($masked),
                'delivery_email_masked' => $masked['email'],
                'delivery_telegram_username_masked' => $masked['telegram'],
                'delivery_whatsapp_number_masked' => $masked['whatsapp'],
                'delivery_email' => null,
                'delivery_telegram_username' => null,
                'delivery_whatsapp_number' => null,
                'encrypted_plain_text_token' => null,
            ])->save();

            ApiTokenRequestEvent::query()->create([
                'api_token_request_id' => $request->id,
                'event' => 'delivery_confirmed',
                'description' => 'Solicitud marcada como entregada; los datos completos de contacto fueron eliminados.',
                'metadata' => ['delivery_channel' => $channel, 'fields_retained' => array_keys(array_filter($masked))],
                'performed_by' => $deliveredBy,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return $request->refresh();
        });
    }

    /** @param array{email: string|null, telegram: string|null, whatsapp: string|null} $masked */
    private function firstMaskedValue(array $masked): ?string
    {
        return $masked['email'] ?? $masked['telegram'] ?? $masked['whatsapp'];
    }
}
