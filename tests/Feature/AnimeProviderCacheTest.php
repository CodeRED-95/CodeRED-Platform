<?php

namespace Tests\Feature;

use App\Services\Anime\Cache\ProviderCacheRepository;
use App\Services\Anime\Data\Anime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class AnimeProviderCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        config([
            'anime.cache.enabled' => true,
            'anime.cache.store' => 'array',
            'anime.cache.mirror_database' => true,
        ]);
    }

    public function test_provider_cache_uses_configured_cache_store_and_mirrors_snapshot_to_database(): void
    {
        $repository = app(ProviderCacheRepository::class);
        $calls = 0;

        $first = $repository->remember('anilist', 'search', 'one-piece', 60, function () use (&$calls): array {
            $calls++;

            return [
                new Anime(id: 'anilist:21', slug: 'one-piece', title: 'ONE PIECE'),
            ];
        });
        $second = $repository->remember('anilist', 'search', 'one-piece', 60, function () use (&$calls): array {
            $calls++;

            return [];
        });

        self::assertSame(1, $calls);
        self::assertSame($first, $second);
        $this->assertDatabaseHas('provider_cache', [
            'provider' => 'anilist',
            'bucket' => 'search',
            'cache_key' => 'one-piece',
            'status' => 'fresh',
        ]);
    }

    public function test_provider_cache_can_bypass_cache_when_disabled(): void
    {
        config(['anime.cache.enabled' => false]);
        $repository = app(ProviderCacheRepository::class);
        $calls = 0;

        $repository->remember('jkanime', 'episodes', 'one-piece:1', 60, function () use (&$calls): array {
            $calls++;

            return [];
        });
        $repository->remember('jkanime', 'episodes', 'one-piece:1', 60, function () use (&$calls): array {
            $calls++;

            return [];
        });

        self::assertSame(2, $calls);
    }
}
