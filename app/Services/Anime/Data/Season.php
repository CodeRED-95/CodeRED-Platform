<?php

namespace App\Services\Anime\Data;

final readonly class Season
{
    public function __construct(
        public string $id,
        public string $animeId,
        public int $number = 1,
        public ?string $title = null,
        public array $episodes = [],
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'anime_id' => $this->animeId,
            'number' => $this->number,
            'title' => $this->title,
            'episodes' => array_map(static fn (Episode $episode): array => $episode->toArray(), $this->episodes),
        ];
    }
}
