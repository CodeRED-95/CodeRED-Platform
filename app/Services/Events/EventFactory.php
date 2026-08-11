<?php

namespace App\Services\Events;

use App\Services\Events\Contracts\UuidGeneratorContract;
use App\Services\Events\DTO\EventData;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class EventFactory
{
    public function __construct(
        private readonly UuidGeneratorContract $uuidGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function make(string $type, array $payload = [], ?string $tenant = null, ?string $source = null, int $version = 1): EventData
    {
        if (! in_array($type, EventType::all(), true)) {
            throw new InvalidArgumentException('Tipo de evento no soportado.');
        }

        return new EventData(
            id: 'evt_'.$this->uuidGenerator->generateV7(),
            type: $type,
            version: $version,
            occurredAt: (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            tenant: $tenant ?? 'default',
            source: $source ?? 'platform',
            payload: $payload,
        );
    }
}
