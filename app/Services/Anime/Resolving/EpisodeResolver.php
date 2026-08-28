<?php

namespace App\Services\Anime\Resolving;

use App\Services\Anime\Data\Episode;

final class EpisodeResolver
{
    /**
     * @param  list<Episode>  $episodes
     */
    public function findByNumber(array $episodes, int $number): ?Episode
    {
        if ($number < 1) {
            return null;
        }

        foreach ($episodes as $episode) {
            if ($episode->number === $number) {
                return $episode;
            }
        }

        return null;
    }
}
