<?php

namespace Tests\Unit\Events;

use App\Models\EventDispatch;
use App\Services\Events\Contracts\EventDeliveryContract;
use App\Services\Events\Contracts\EventDispatchRepositoryContract;
use App\Services\Events\DTO\EventData;
use App\Services\Events\EventDispatcher;
use App\Services\Events\EventFactory;
use App\Services\Events\EventType;
use App\Services\Events\Infrastructure\UuidV7Generator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventDispatcherTest extends TestCase
{
    #[Test]
    public function it_builds_and_delivers_a_canonical_event(): void
    {
        $repository = $this->createMock(EventDispatchRepositoryContract::class);
        $delivery = $this->createMock(EventDeliveryContract::class);
        $dispatchRecord = new EventDispatch;
        $dispatchRecord->id = 11;

        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(function (EventData $event): bool {
                return $event->type === EventType::TOKEN_REQUEST_CREATED
                    && $event->source === 'platform'
                    && $event->tenant === 'default'
                    && $event->payload === ['request_id' => 123];
            }))
            ->willReturn($dispatchRecord);

        $delivery->expects($this->once())
            ->method('deliver')
            ->with($dispatchRecord, $this->isInstanceOf(EventData::class))
            ->willReturn(true);

        $dispatcher = new EventDispatcher(new EventFactory(new UuidV7Generator), $repository, $delivery, true, false);

        $event = $dispatcher->dispatch(EventType::TOKEN_REQUEST_CREATED, ['request_id' => 123]);

        $this->assertSame(EventType::TOKEN_REQUEST_CREATED, $event->type);
    }
}
