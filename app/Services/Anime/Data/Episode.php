<?php

namespace App\Services\Anime\Data;

final readonly class Episode
{
    public function __construct(
        public string $id,
        public string $animeId,
        public int $number,
        public ?string $title = null,
        public string $language = 'sub',
        public array $servers = [],
        public ?string $thumbnail = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'anime_id' => $this->animeId,
            'number' => $this->number,
            'title' => $this->title,
            'language' => $this->language,
            'servers' => array_map(static fn (Server $server): array => $server->toArray(), $this->servers),
            'thumbnail' => $this->thumbnail,
        ];
    }
}
