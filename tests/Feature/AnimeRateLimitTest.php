<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class AnimeRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        config([
            'anime.enabled' => true,
            'anime.cache.enabled' => false,
            'anime.providers.jkanime.base_url' => 'https://jkanime.test',
            'anime.providers.jkanime.allowed_hosts' => ['jkanime.test'],
            'anime.providers.jkanime.stream_allowed_hosts' => ['jkanime.test'],
            'anime.rate_limits.stream' => 1,
        ]);
    }

    public function test_stream_rate_limit_uses_anime_stream_bucket_per_token(): void
    {
        Http::fake([
            'jkanime.test/one-piece/1175' => Http::response($this->episodeHtml(), 200, ['Content-Type' => 'text/html']),
        ]);

        $created = User::factory()->create()->createToken('Anime prueba', ['anime:read']);
        RateLimiter::clear('anime-stream:token:'.$created->accessToken->getKey());

        $this->withToken($created->plainTextToken)
            ->getJson('/api/v1/anime/jkanime:one-piece/episodes/1175/stream?server=desu')
            ->assertOk();
        auth()->forgetGuards();

        $this->withToken($created->plainTextToken)
            ->getJson('/api/v1/anime/jkanime:one-piece/episodes/1175/stream?server=desu')
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Se superó el límite de solicitudes.');
    }

    private function episodeHtml(): string
    {
        return <<<'HTML'
<html><head><title>One Piece 1175 Sub Español Online gratis - JkAnime</title></head><body>
<script>
var video = [];
video[0] = '<iframe class="player_conte" src="https://jkanime.test/media/one-piece-1175.m3u8"></iframe>';
</script>
</body></html>
HTML;
    }
}
