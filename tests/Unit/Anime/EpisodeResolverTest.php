<?php

namespace Tests\Unit\Anime;

use App\Services\Anime\Data\Episode;
use App\Services\Anime\Resolving\EpisodeResolver;
use PHPUnit\Framework\TestCase;

final class EpisodeResolverTest extends TestCase
{
    public function test_finds_episode_by_number_without_guessing_invalid_numbers(): void
    {
        $resolver = new EpisodeResolver;
        $episodes = [
            new Episode(id: 'jkanime:one-piece:1174', animeId: 'jkanime:one-piece', number: 1174),
            new Episode(id: 'jkanime:one-piece:1175', animeId: 'jkanime:one-piece', number: 1175),
        ];

        self::assertSame($episodes[1], $resolver->findByNumber($episodes, 1175));
        self::assertNull($resolver->findByNumber($episodes, 0));
        self::assertNull($resolver->findByNumber($episodes, 9999));
    }
}
