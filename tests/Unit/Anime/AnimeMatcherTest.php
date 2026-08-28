<?php

namespace Tests\Unit\Anime;

use App\Services\Anime\Data\Anime;
use App\Services\Anime\Data\Metadata;
use App\Services\Anime\Matching\AnimeMatcher;
use PHPUnit\Framework\TestCase;

final class AnimeMatcherTest extends TestCase
{
    public function test_scores_exact_external_id_as_full_match(): void
    {
        $matcher = new AnimeMatcher;

        $source = new Anime(
            id: 'anilist:21',
            slug: 'one-piece',
            title: 'ONE PIECE',
            metadata: new Metadata(externalIds: ['anilist_id' => 21]),
        );
        $candidate = new Anime(
            id: 'jkanime:one-piece',
            slug: 'one-piece',
            title: 'One Piece',
            metadata: new Metadata(externalIds: ['anilist_id' => '21', 'jkanime_id' => '201']),
        );

        self::assertSame(1.0, $matcher->score($source, $candidate));
    }

    public function test_best_match_supports_synonyms_and_alternative_titles(): void
    {
        $matcher = new AnimeMatcher;
        $source = new Anime(
            id: 'anilist:16498',
            slug: 'shingeki-no-kyojin',
            title: 'Shingeki no Kyojin',
            titles: ['english' => 'Attack on Titan'],
            metadata: new Metadata(synonyms: ['AOT']),
        );
        $weakCandidate = new Anime(
            id: 'jkanime:naruto',
            slug: 'naruto',
            title: 'Naruto',
        );
        $strongCandidate = new Anime(
            id: 'jkanime:attack-on-titan',
            slug: 'attack-on-titan',
            title: 'Attack on Titan',
        );

        $match = $matcher->bestMatch($source, [$weakCandidate, $strongCandidate]);

        self::assertSame($strongCandidate, $match);
    }

    public function test_best_match_returns_null_when_score_is_below_threshold(): void
    {
        $matcher = new AnimeMatcher;

        $match = $matcher->bestMatch(
            new Anime(id: 'anilist:1', slug: 'cowboy-bebop', title: 'Cowboy Bebop'),
            [new Anime(id: 'jkanime:one-piece', slug: 'one-piece', title: 'One Piece')],
        );

        self::assertNull($match);
    }
}
