<?php

namespace App\Services\Anime\Data;

final readonly class Metadata
{
    public function __construct(
        public array $titles = [],
        public array $synonyms = [],
        public array $genres = [],
        public array $studios = [],
        public array $relations = [],
        public array $characters = [],
        public array $externalIds = [],
        public ?string $description = null,
        public ?string $season = null,
        public ?string $status = null,
        public ?int $year = null,
        public ?int $episodes = null,
    ) {}

    public function toArray(): array
    {
        return [
            'titles' => $this->titles,
            'synonyms' => $this->synonyms,
            'genres' => $this->genres,
            'studios' => $this->studios,
            'relations' => $this->relations,
            'characters' => $this->characters,
            'external_ids' => $this->externalIds,
            'description' => $this->description,
            'season' => $this->season,
            'status' => $this->status,
            'year' => $this->year,
            'episodes' => $this->episodes,
        ];
    }
}
