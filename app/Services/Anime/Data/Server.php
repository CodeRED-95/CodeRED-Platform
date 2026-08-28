<?php

namespace App\Services\Anime\Data;

final readonly class Server
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type = 'embed',
        public string $language = 'sub',
        public ?string $url = null,
        public ?string $provider = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'language' => $this->language,
            'url' => $this->url,
            'provider' => $this->provider,
        ];
    }
}
