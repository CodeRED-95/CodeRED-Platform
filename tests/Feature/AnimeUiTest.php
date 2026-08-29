<?php

namespace Tests\Feature;

use App\Livewire\Admin\Anime\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

final class AnimeUiTest extends TestCase
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
        ]);
    }

    public function test_authenticated_users_can_open_anime_panel(): void
    {
        $user = User::factory()->create(['status' => 'active', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('admin.anime.index'))
            ->assertOk()
            ->assertSee('CodeRED Anime')
            ->assertSee('Buscar anime');
    }

    public function test_anime_panel_searches_selects_episode_and_resolves_stream(): void
    {
        $this->fakeAnimeHttp();

        Livewire::actingAs(User::factory()->create(['status' => 'active', 'is_active' => true]))
            ->test(Index::class)
            ->set('query', 'one piece')
            ->call('search')
            ->assertSet('results.0.id', 'jkanime:one-piece')
            ->call('selectAnime', 'jkanime:one-piece')
            ->assertSet('anime.id', 'jkanime:one-piece')
            ->assertSet('showAnimeModal', true)
            ->assertSet('seasons.0.title', 'Temporada unica')
            ->assertSet('episodes.0.number', 1175)
            ->call('selectEpisode', 1175)
            ->assertSet('episode.number', 1175)
            ->assertSet('servers.0.name', 'Desu')
            ->call('selectServer', 'jkanime:one-piece:1175:desu')
            ->call('resolveStream')
            ->assertSet('stream.format', 'm3u8')
            ->assertSee('Fuente resuelta');
    }

    public function test_anime_panel_rejects_url_like_searches_before_catalog_call(): void
    {
        Http::fake();

        Livewire::actingAs(User::factory()->create(['status' => 'active', 'is_active' => true]))
            ->test(Index::class)
            ->set('query', 'http://169.254.169.254/latest')
            ->call('search')
            ->assertHasErrors(['query']);

        Http::assertNothingSent();
    }

    private function fakeAnimeHttp(): void
    {
        Http::fake([
            'jkanime.test/buscar?q=one%20piece' => Http::response($this->searchHtml(), 200, ['Content-Type' => 'text/html']),
            'jkanime.test/one-piece/' => Http::response($this->animeHtml(), 200, ['Content-Type' => 'text/html']),
            'jkanime.test/ajax/episodes/201/1' => Http::response(['html' => $this->episodesHtml()], 200, ['Content-Type' => 'application/json']),
            'jkanime.test/one-piece/1175' => Http::response($this->episodeHtml(), 200, ['Content-Type' => 'text/html']),
            'graphql.anilist.test' => Http::response([
                'data' => [
                    'Page' => ['media' => [$this->anilistMedia()]],
                ],
            ]),
        ]);
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
<meta name="description" content="Piratas y aventura."/>
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
<html><head><title>One Piece 1175 Sub Espanol Online gratis - JkAnime</title></head><body>
<a data-id="0">Desu</a>
<script>
var video = [];
video[0] = '<iframe class="player_conte" src="https://jkanime.test/media/one-piece-1175.m3u8"></iframe>';
</script>
</body></html>
HTML;
    }

    private function anilistMedia(): array
    {
        return [
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
        ];
    }
}
