<?php

namespace App\Jobs;

use App\Services\Events\Contracts\EventDeliveryContract;
use App\Services\Events\Contracts\EventDispatchRepositoryContract;
use App\Services\Events\DTO\EventData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DispatchPlatformEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /**
     * @param array<string, mixed> $eventPayload
     */
    public function __construct(public int $dispatchId, public array $eventPayload)
    {
    }

    public function handle(EventDeliveryContract $delivery, EventDispatchRepositoryContract $repository): void
    {
        $dispatch = $repository->findOrFail($this->dispatchId);
        $event = new EventData(
            id: (string) $this->eventPayload['id'],
            type: (string) $this->eventPayload['type'],
            version: (int) $this->eventPayload['version'],
            occurredAt: (string) $this->eventPayload['occurred_at'],
            tenant: (string) $this->eventPayload['tenant'],
            source: (string) $this->eventPayload['source'],
            payload: is_array($this->eventPayload['payload']) ? $this->eventPayload['payload'] : [],
        );

        $delivery->deliver($dispatch, $event);
    }
}
