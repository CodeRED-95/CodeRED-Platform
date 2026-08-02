<?php

namespace App\Services\Events;

use App\Jobs\DispatchPlatformEventJob;
use App\Services\Events\Contracts\EventDeliveryContract;
use App\Services\Events\Contracts\EventDispatcherContract;
use App\Services\Events\Contracts\EventDispatchRepositoryContract;
use App\Services\Events\DTO\EventData;

final class EventDispatcher implements EventDispatcherContract
{
    public function __construct(
        private readonly EventFactory $factory,
        private readonly EventDispatchRepositoryContract $repository,
        private readonly EventDeliveryContract $delivery,
        private readonly bool $enabled = true,
        private readonly bool $queueEnabled = true,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $type, array $payload = [], ?string $tenant = null, ?string $source = null): EventData
    {
        $event = $this->factory->make($type, $payload, $tenant, $source);
        $dispatch = $this->repository->create($event);

        if (! $this->enabled) {
            return $event;
        }

        if ($this->queueEnabled) {
            DispatchPlatformEventJob::dispatch($dispatch->id, $event->toArray());
            return $event;
        }

        $this->delivery->deliver($dispatch, $event);

        return $event;
    }
}
