<?php

namespace App\Services\Anime\Catalog;

use App\Services\Anime\Data\Anime;
use App\Services\Anime\Data\Episode;
use App\Services\Anime\Data\Metadata;
use App\Services\Anime\Data\Season;
use App\Services\Anime\Data\Server;
use App\Services\Anime\Data\Stream;
use App\Services\Anime\Matching\AnimeMatcher;
use App\Services\Anime\Providers\AniListProvider;
use App\Services\Anime\Providers\JkAnimeProvider;

final class AnimeCatalogService
{
    public function __construct(
        private readonly JkAnimeProvider $streamingProvider,
        private readonly AniListProvider $metadataProvider,
        private readonly AnimeMatcher $matcher,
    ) {}

    /** @return list<Anime> */
    public function search(string $query): array
    {
        $streamingResults = $this->streamingProvider->search($query);
        $metadataResults = $this->metadataProvider->search($query);

        if ($streamingResults === []) {
            return $metadataResults;
        }

        return array_map(
            fn (Anime $anime): Anime => $this->mergeMetadata($anime, $this->matcher->bestMatch($anime, $metadataResults, 0.78)),
            $streamingResults,
        );
    }

    public function getAnime(string $id): ?Anime
    {
        if (str_starts_with($id, 'anilist:') || preg_match('/^[1-9][0-9]{0,9}$/', $id) === 1) {
            return $this->metadataProvider->getAnime($id);
        }

        $anime = $this->streamingProvider->getAnime($id);
        if (! $anime instanceof Anime) {
            return null;
        }

        $metadata = $this->matcher->bestMatch($anime, $this->metadataProvider->search($anime->title), 0.78);

        return $this->mergeMetadata($anime, $metadata);
    }

    /** @return list<Season> */
    public function getSeasons(string $animeId): array
    {
        $episodes = $this->getEpisodes($animeId);

        return [
            new Season(
                id: $this->normalizeStreamingId($animeId).':season:1',
                animeId: $this->normalizeStreamingId($animeId),
                number: 1,
                title: 'Temporada 1',
                episodes: $episodes,
            ),
        ];
    }

    /** @return list<Episode> */
    public function getEpisodes(string $animeId, ?int $page = null): array
    {
        return $this->streamingProvider->getEpisodes($animeId, $page);
    }

    public function getEpisode(string $animeId, int $episode): ?Episode
    {
        return $this->streamingProvider->getEpisode($animeId, $episode);
    }

    /** @return list<Server> */
    public function getServers(string $animeId, int $episode): array
    {
        return $this->streamingProvider->getServers($animeId, $episode);
    }

    public function getStream(string $animeId, int $episode, string $server): ?Stream
    {
        return $this->streamingProvider->getStream($animeId, $episode, $server);
    }

    private function mergeMetadata(Anime $anime, ?Anime $metadata): Anime
    {
        if (! $metadata instanceof Anime) {
            return $anime;
        }

        return new Anime(
            id: $anime->id,
            slug: $anime->slug,
            title: $anime->title,
            titles: array_filter([...$metadata->titles, ...$anime->titles]),
            genres: $metadata->genres !== [] ? $metadata->genres : $anime->genres,
            year: $metadata->year ?? $anime->year,
            description: $metadata->description ?? $anime->description,
            poster: $anime->poster ?? $metadata->poster,
            banner: $metadata->banner ?? $anime->banner,
            episodes: $anime->episodes ?? $metadata->episodes,
            status: $anime->status ?? $metadata->status,
            metadata: $this->mergedMetadata($anime->metadata, $metadata->metadata),
        );
    }

    private function mergedMetadata(?Metadata $current, ?Metadata $metadata): ?Metadata
    {
        if (! $metadata instanceof Metadata) {
            return $current;
        }

        if (! $current instanceof Metadata) {
            return $metadata;
        }

        return new Metadata(
            titles: array_filter([...$metadata->titles, ...$current->titles]),
            synonyms: array_values(array_unique([...$metadata->synonyms, ...$current->synonyms])),
            genres: $metadata->genres !== [] ? $metadata->genres : $current->genres,
            studios: $metadata->studios,
            relations: $metadata->relations,
            characters: $metadata->characters,
            externalIds: array_filter([...$metadata->externalIds, ...$current->externalIds]),
            description: $metadata->description ?? $current->description,
            season: $metadata->season ?? $current->season,
            status: $metadata->status ?? $current->status,
            year: $metadata->year ?? $current->year,
            episodes: $current->episodes ?? $metadata->episodes,
        );
    }

    private function normalizeStreamingId(string $animeId): string
    {
        return str_starts_with($animeId, 'jkanime:') ? $animeId : 'jkanime:'.trim($animeId, '/');
    }
}
