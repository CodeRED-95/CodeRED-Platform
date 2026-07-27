<?php

namespace App\Console\Commands;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Jobs\NotifyN8nTokenRequestStatus;
use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use App\Services\Integrations\N8nTelegramTokenSettings;
use Illuminate\Console\Command;

class ExpirePendingTokenRequestsCommand extends Command
{
    protected $signature = 'tokens:expire-pending-requests';

    protected $description = 'Marca como vencidas solicitudes de tokens pendientes y limpia tokens cifrados antiguos.';

    public function handle(N8nTelegramTokenSettings $settings): int
    {
        $expired = 0;
        $cutoff = now()->subMinutes((int) $settings->get('approval_timeout_minutes', 1440));
        ApiTokenRequest::query()->where('status', ApiTokenRequestStatus::Pending->value)->where('requested_at', '<', $cutoff)->chunkById(100, function ($requests) use (&$expired): void {
            foreach ($requests as $request) {
                $request->forceFill(['status' => ApiTokenRequestStatus::Expired, 'encrypted_plain_text_token' => null])->save();
                ApiTokenRequestEvent::query()->create(['api_token_request_id' => $request->id, 'event' => 'expired', 'description' => 'Solicitud vencida automáticamente.', 'metadata' => [], 'created_at' => now()]);
                NotifyN8nTokenRequestStatus::dispatch($request->id, 'token_request.expired');
                $expired++;
            }
        });
        $cleaned = ApiTokenRequest::query()->whereNotNull('encrypted_plain_text_token')->whereNotNull('result_retrieved_at')->update(['encrypted_plain_text_token' => null]);
        $failed = ApiTokenRequest::query()->where('delivery_status', ApiTokenRequestDeliveryStatus::Pending->value)->where('delivery_attempts', '>=', 5)->update(['delivery_status' => ApiTokenRequestDeliveryStatus::Failed]);
        $this->info("Solicitudes vencidas: {$expired}. Tokens cifrados limpiados: {$cleaned}. Entregas fallidas: {$failed}.");

        return self::SUCCESS;
    }
}
