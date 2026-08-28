<?php

namespace App\Services\Anime\Data;

final readonly class Anime
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public array $titles = [],
        public array $genres = [],
        public ?int $year = null,
        public ?string $description = null,
        public ?string $poster = null,
        public ?string $banner = null,
        public ?int $episodes = null,
        public ?string $status = null,
        public ?Metadata $metadata = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'titles' => $this->titles,
            'year' => $this->year,
            'genres' => $this->genres,
            'description' => $this->description,
            'poster' => $this->poster,
            'banner' => $this->banner,
            'episodes' => $this->episodes,
            'status' => $this->status,
            'metadata' => $this->metadata?->toArray(),
        ];
    }
}
