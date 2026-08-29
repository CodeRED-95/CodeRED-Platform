<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class AnimeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        config([
            'anime.enabled' => true,
            'anime.cache.enabled' => false,
            'anime.providers.jkanime.enabled' => true,
            'anime.providers.jkanime.base_url' => 'https://jkanime.test',
            'anime.providers.jkanime.allowed_hosts' => ['jkanime.test'],
            'anime.providers.jkanime.stream_allowed_hosts' => ['jkanime.test'],
            'anime.providers.jkanime.user_agent' => 'CodeRED-Test',
            'anime.providers.anilist.enabled' => true,
            'anime.providers.anilist.base_url' => 'https://graphql.anilist.test',
            'anime.providers.anilist.allowed_hosts' => ['graphql.anilist.test'],
            'anime.providers.anilist.user_agent' => 'CodeRED-Test',
            'anime.providers.anilist.search_limit' => 3,
            'anime.rate_limits.search' => 30,
            'anime.rate_limits.metadata' => 60,
            'anime.rate_limits.episodes' => 60,
            'anime.rate_limits.stream' => 20,
        ]);
    }

    public function test_search_returns_uniform_codered_response(): void
    {
        $this->fakeSearchHttp();

        $this->withToken($this->token(['anime:read']))
            ->getJson('/api/v1/anime/search?q=one%20piece')
            ->assertOk()
            ->assertJsonPath('meta.provider', 'codered')
            ->assertJsonPath('meta.operation', 'search')
            ->assertJsonPath('data.0.id', 'jkanime:one-piece')
            ->assertJsonPath('data.0.metadata.external_ids.anilist_id', 21);
    }

    public function test_playable_search_does_not_return_metadata_only_results(): void
    {
        Http::fake([
            'jkanime.test/buscar?q=reiwa%20no%20dara-san' => Http::response('<html><body></body></html>', 200, ['Content-Type' => 'text/html']),
            'graphql.anilist.test' => Http::response([
                'data' => [
                    'Page' => ['media' => [$this->anilistMedia([
                        'id' => 203880,
                        'title' => ['romaji' => 'Reiwa no Dara-san', 'userPreferred' => 'Reiwa no Dara-san'],
                    ])]],
                ],
            ]),
        ]);

        $this->withToken($this->token(['anime:read']))
            ->getJson('/api/v1/anime/search?q=reiwa%20no%20dara-san&playable=1')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.count', 0);
    }

    public function test_metadata_endpoint_returns_anime_detail(): void
    {
        Http::fake([
            'jkanime.test/one-piece/' => Http::response($this->animeHtml(), 200, ['Content-Type' => 'text/html']),
            'graphql.anilist.test' => Http::response([
                'data' => [
                    'Page' => ['media' => [$this->anilistMedia()]],
                ],
            ]),
        ]);

        $this->withToken($this->token(['anime:read']))
            ->getJson('/api/v1/anime/jkanime:one-piece')
            ->assertOk()
            ->assertJsonPath('meta.operation', 'metadata')
            ->assertJsonPath('data.id', 'jkanime:one-piece')
            ->assertJsonPath('data.title', 'One Piece')
            ->assertJsonPath('data.metadata.external_ids.jkanime_id', '201')
            ->assertJsonPath('data.metadata.external_ids.anilist_id', 21);
    }

    public function test_episodes_servers_and_stream_endpoints_return_provider_data(): void
    {
        Http::fake([
            'jkanime.test/one-piece/' => Http::response($this->animeHtml(), 200, ['Content-Type' => 'text/html']),
            'jkanime.test/ajax/episodes/201/74' => Http::response(['html' => $this->episodesHtml()], 200, ['Content-Type' => 'application/json']),
            'jkanime.test/one-piece/1175' => Http::response($this->episodeHtml(), 200, ['Content-Type' => 'text/html']),
        ]);

        $token = $this->token(['anime:read']);

        $this->withToken($token)
            ->getJson('/api/v1/anime/jkanime:one-piece/episodes?page=74')
            ->assertOk()
            ->assertJsonPath('meta.operation', 'episodes')
            ->assertJsonPath('data.0.number', 1175);
        auth()->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/anime/jkanime:one-piece/episodes/1175/servers')
            ->assertOk()
            ->assertJsonPath('meta.operation', 'servers')
            ->assertJsonPath('data.0.name', 'Desu')
            ->assertJsonPath('data.0.type', 'stream');
        auth()->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/anime/jkanime:one-piece/episodes/1175/stream?server=desu')
            ->assertOk()
            ->assertJsonPath('meta.operation', 'stream')
            ->assertJsonPath('data.type', 'hls')
            ->assertJsonPath('data.format', 'm3u8')
            ->assertJsonPath('data.headers', []);
    }

    public function test_anime_api_requires_authentication_and_anime_ability(): void
    {
        $this->getJson('/api/v1/anime/search?q=one%20piece')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'No autenticado.']);

        $this->withToken($this->token(['profile:read']))
            ->getJson('/api/v1/anime/search?q=one%20piece')
            ->assertForbidden()
            ->assertExactJson(['message' => 'El token no tiene permiso para realizar esta acción.']);
    }

    public function test_search_input_validation_blocks_suspicious_queries_before_provider_request(): void
    {
        Http::fake();

        $this->withToken($this->token(['anime:read']))
            ->getJson('/api/v1/anime/search?q=http://169.254.169.254/latest')
            ->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_stream_rate_limit_uses_anime_stream_bucket_per_token(): void
    {
        config(['anime.rate_limits.stream' => 1]);

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

    private function fakeSearchHttp(): void
    {
        Http::fake([
            'jkanime.test/buscar?q=one%20piece' => Http::response($this->searchHtml(), 200, ['Content-Type' => 'text/html']),
            'graphql.anilist.test' => Http::response([
                'data' => [
                    'Page' => ['media' => [$this->anilistMedia()]],
                ],
            ]),
        ]);
    }

    private function token(array $abilities): string
    {
        return User::factory()->create()->createToken('Anime prueba', $abilities)->plainTextToken;
    }

    private function searchHtml(): string
    {
        return <<<'HTML'
<html><body>
<a href="https://jkanime.test/one-piece/">
    <img src="https://jkanime.test/one-piece.jpg" alt="One Piece">
    <h5>One Piece</h5>
</a>
</body></html>
HTML;
    }

    private function animeHtml(): string
    {
        return <<<'HTML'
<html><head>
<title>One Piece - anime One Piece online JkAnime</title>
<meta name="csrf-token" content="csrf-123">
<meta name="description" content="Una historia epica de piratas"/>
<meta property="og:title" content="One Piece - anime One Piece online JkAnime"/>
<meta property="og:image" content="https://jkanime.test/one-piece.jpg"/>
</head><body>
<li><span>Episodios:</span> 1175</li>
<script>$.ajax({ type: "POST", url: "https://jkanime.test/ajax/episodes/201/"+pag });</script>
</body></html>
HTML;
    }

    private function episodesHtml(): string
    {
        return <<<'HTML'
<a href="https://jkanime.test/one-piece/1175"><img src="https://jkanime.test/1175.jpg">Episodio 1175</a>
HTML;
    }

    private function episodeHtml(): string
    {
        return <<<'HTML'
<html><head><title>One Piece 1175 Sub Español Online gratis — JkAnime</title></head><body>
<a data-id="0">Desu</a>
<script>
var video = [];
video[0] = '<iframe class="player_conte" src="https://jkanime.test/media/one-piece-1175.m3u8"></iframe>';
</script>
</body></html>
HTML;
    }

    private function anilistMedia(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 21,
            'title' => [
                'romaji' => 'One Piece',
                'english' => 'One Piece',
                'native' => 'ONE PIECE',
                'userPreferred' => 'One Piece',
            ],
            'synonyms' => ['OP'],
            'description' => 'Piratas<br><b>Aventura</b>',
            'coverImage' => ['large' => 'https://img.anilist.test/one-piece.jpg', 'medium' => null, 'color' => null],
            'bannerImage' => 'https://img.anilist.test/banner.jpg',
            'genres' => ['Adventure'],
            'season' => 'FALL',
            'seasonYear' => 1999,
            'status' => 'RELEASING',
            'episodes' => null,
            'studios' => ['nodes' => [['id' => 18, 'name' => 'Toei Animation']]],
            'relations' => ['edges' => []],
            'characters' => ['edges' => []],
        ], $overrides);
    }
}
