<?php

namespace Tests\Unit\Events;

use App\Services\Events\DTO\EventData;
use App\Services\Events\EventFactory;
use App\Services\Events\EventType;
use App\Services\Events\Infrastructure\UuidV7Generator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventFactoryTest extends TestCase
{
    #[Test]
    public function it_builds_the_canonical_event_envelope(): void
    {
        $factory = new EventFactory(new UuidV7Generator);

        $event = $factory->make(EventType::TOKEN_REQUEST_CREATED, ['request_id' => 123]);

        $this->assertInstanceOf(EventData::class, $event);
        $this->assertSame('platform', $event->source);
        $this->assertSame('default', $event->tenant);
        $this->assertSame(1, $event->version);
        $this->assertSame(EventType::TOKEN_REQUEST_CREATED, $event->type);
        $this->assertSame(['request_id' => 123], $event->payload);
        $this->assertMatchesRegularExpression('/^evt_[0-9a-f-]{36}$/', $event->id);
        $this->assertMatchesRegularExpression('/Z$/', $event->occurredAt);
    }
}
