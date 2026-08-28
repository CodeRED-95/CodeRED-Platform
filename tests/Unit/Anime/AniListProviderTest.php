<?php

namespace Tests\Unit\Anime;

use App\Services\Anime\Providers\AniListProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AniListProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        config([
            'anime.enabled' => true,
            'anime.cache.enabled' => false,
            'anime.providers.anilist.enabled' => true,
            'anime.providers.anilist.base_url' => 'https://graphql.anilist.test',
            'anime.providers.anilist.allowed_hosts' => ['graphql.anilist.test'],
            'anime.providers.anilist.user_agent' => 'CodeRED-Test',
            'anime.providers.anilist.search_limit' => 3,
            'anime.request_timeout' => 5,
            'anime.connect_timeout' => 2,
        ]);
    }

    public function test_search_queries_anilist_graphql_and_normalizes_metadata(): void
    {
        Http::fake([
            'graphql.anilist.test' => Http::response([
                'data' => [
                    'Page' => [
                        'media' => [$this->mediaPayload()],
                    ],
                ],
            ]),
        ]);

        $results = app(AniListProvider::class)->search('one piece');

        self::assertCount(1, $results);
        self::assertSame('anilist:21', $results[0]->id);
        self::assertSame('ONE PIECE', $results[0]->title);
        self::assertSame('Adventure', $results[0]->genres[0]);
        self::assertSame('Toei Animation', $results[0]->metadata?->studios[0]['name'] ?? null);
        self::assertSame('PREQUEL', $results[0]->metadata?->relations[0]['title'] ?? null);
        self::assertSame('Monkey D. Luffy', $results[0]->metadata?->characters[0]['name'] ?? null);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && (string) $request->url() === 'https://graphql.anilist.test'
            && $request['variables']['search'] === 'one piece');
    }

    public function test_get_anime_accepts_prefixed_or_numeric_anilist_id(): void
    {
        Http::fake([
            'graphql.anilist.test' => Http::response([
                'data' => ['Media' => $this->mediaPayload()],
            ]),
        ]);

        $anime = app(AniListProvider::class)->getAnime('anilist:21');

        self::assertNotNull($anime);
        self::assertSame('anilist:21', $anime->id);
        self::assertSame(21, $anime->metadata?->externalIds['anilist_id'] ?? null);
        self::assertSame('finished', $anime->status);
        self::assertStringNotContainsString('<br>', (string) $anime->description);
    }

    public function test_metadata_provider_does_not_expose_episode_or_streaming_capabilities(): void
    {
        $provider = app(AniListProvider::class);

        self::assertSame([], $provider->getEpisodes('anilist:21'));
        self::assertNull($provider->getEpisode('anilist:21', 1));
        self::assertSame([], $provider->getServers('anilist:21', 1));
        self::assertNull($provider->getStream('anilist:21', 1, 'desu'));
    }

    public function test_provider_rejects_base_url_outside_allowlist_before_http_request(): void
    {
        config(['anime.providers.anilist.base_url' => 'https://169.254.169.254']);
        Http::fake();

        self::assertSame([], app(AniListProvider::class)->search('one piece'));
        Http::assertNothingSent();
    }

    private function mediaPayload(): array
    {
        return [
            'id' => 21,
            'title' => [
                'romaji' => 'ONE PIECE',
                'english' => 'ONE PIECE',
                'native' => 'ONE PIECE',
                'userPreferred' => 'ONE PIECE',
            ],
            'synonyms' => ['OP', 'Wan Pisu'],
            'description' => 'Gold Roger<br><br><b>included specials</b>',
            'coverImage' => [
                'large' => 'https://img.anilist.test/one-piece.jpg',
                'medium' => 'https://img.anilist.test/one-piece-small.jpg',
                'color' => '#f1c40f',
            ],
            'bannerImage' => 'https://img.anilist.test/one-piece-banner.jpg',
            'genres' => ['Adventure', 'Action'],
            'season' => 'FALL',
            'seasonYear' => 1999,
            'status' => 'FINISHED',
            'episodes' => 1200,
            'studios' => [
                'nodes' => [
                    ['id' => 18, 'name' => 'Toei Animation'],
                ],
            ],
            'relations' => [
                'edges' => [
                    [
                        'relationType' => 'PREQUEL',
                        'node' => [
                            'id' => 459,
                            'type' => 'ANIME',
                            'format' => 'SPECIAL',
                            'title' => [
                                'romaji' => 'PREQUEL',
                                'english' => null,
                                'native' => null,
                                'userPreferred' => 'PREQUEL',
                            ],
                        ],
                    ],
                ],
            ],
            'characters' => [
                'edges' => [
                    [
                        'role' => 'MAIN',
                        'node' => [
                            'id' => 40,
                            'name' => [
                                'full' => 'Monkey D. Luffy',
                                'native' => 'モンキー・D・ルフィ',
                            ],
                            'image' => ['medium' => 'https://img.anilist.test/luffy.jpg'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
