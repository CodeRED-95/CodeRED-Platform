<?php

namespace App\Services\Events\DTO;

final readonly class EventDispatchData
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $eventId,
        public string $type,
        public string $status,
        public int $attempts,
        public ?int $responseCode,
        public ?string $responseBody,
        public ?string $error,
        public array $payload,
        public string $occurredAt,
        public string $tenant,
        public string $source,
        public ?int $durationMs = null,
    ) {
    }
}
