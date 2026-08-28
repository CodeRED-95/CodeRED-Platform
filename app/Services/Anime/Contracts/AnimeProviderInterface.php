<?php

namespace App\Services\Anime\Contracts;

use App\Services\Anime\Data\Anime;
use App\Services\Anime\Data\Episode;
use App\Services\Anime\Data\Stream;

interface AnimeProviderInterface
{
    public function search(string $query): array;

    public function getAnime(string $id): ?Anime;

    public function getEpisodes(string $animeId, ?int $page = null): array;

    public function getEpisode(string $animeId, int $episode): ?Episode;

    public function getServers(string $animeId, int $episode): array;

    public function getStream(string $animeId, int $episode, string $server): ?Stream;
}
