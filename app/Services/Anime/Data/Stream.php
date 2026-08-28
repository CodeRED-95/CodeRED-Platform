<?php

namespace App\Services\Anime\Data;

final readonly class Stream
{
    public function __construct(
        public string $url,
        public string $type,
        public string $format,
        public array $headers = [],
        public ?string $expiresAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'type' => $this->type,
            'format' => $this->format,
            'headers' => $this->headers,
            'expires_at' => $this->expiresAt,
        ];
    }
}
