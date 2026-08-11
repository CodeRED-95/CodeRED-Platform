<?php

namespace App\Services\Events\DTO;

final readonly class EventData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public int $version,
        public string $occurredAt,
        public string $tenant,
        public string $source,
        public array $payload,
    ) {}

    /**
     * @return array{id: string, type: string, version: int, occurred_at: string, tenant: string, source: string, payload: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'version' => $this->version,
            'occurred_at' => $this->occurredAt,
            'tenant' => $this->tenant,
            'source' => $this->source,
            'payload' => $this->payload,
        ];
    }
}
