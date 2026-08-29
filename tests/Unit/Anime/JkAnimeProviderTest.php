<?php

namespace Tests\Unit\Anime;

use App\Services\Anime\Contracts\AnimeProviderInterface;
use App\Services\Anime\Data\Server;
use App\Services\Anime\Providers\JkAnimeProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class JkAnimeProviderTest extends TestCase
{
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
            'anime.providers.jkanime.user_agent' => 'CodeRED-Test',
            'anime.request_timeout' => 5,
            'anime.connect_timeout' => 2,
        ]);
    }

    public function test_contract_resolves_to_jkanime_provider(): void
    {
        self::assertInstanceOf(JkAnimeProvider::class, app(AnimeProviderInterface::class));
    }

    public function test_search_uses_current_public_search_route_and_normalizes_results(): void
    {
        Http::fake([
            'jkanime.test/buscar?q=one%20piece' => Http::response($this->searchHtml(), 200, ['Content-Type' => 'text/html']),
        ]);

        $results = app(JkAnimeProvider::class)->search('one piece');

        self::assertCount(1, $results);
        self::assertSame('jkanime:one-piece', $results[0]->id);
        self::assertSame('One Piece', $results[0]->title);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && (string) $request->url() === 'https://jkanime.test/buscar?q=one%20piece');
    }

    public function test_search_ignores_navigation_links_before_real_results(): void
    {
        Http::fake([
            'jkanime.test/buscar?q=one%20punch%20man' => Http::response($this->searchHtmlWithNavigation(), 200, ['Content-Type' => 'text/html']),
        ]);

        $results = app(JkAnimeProvider::class)->search('one punch man');

        self::assertCount(1, $results);
        self::assertSame('jkanime:one-punch-man', $results[0]->id);
        self::assertSame('One Punch Man', $results[0]->title);
    }

    public function test_search_parses_current_jkanime_card_markup(): void
    {
        Http::fake([
            'jkanime.test/buscar?q=one%20piece' => Http::response($this->currentSearchCardHtml(), 200, ['Content-Type' => 'text/html']),
        ]);

        $results = app(JkAnimeProvider::class)->search('one piece');

        self::assertCount(2, $results);
        self::assertSame('jkanime:one-piece', $results[0]->id);
        self::assertSame('One Piece', $results[0]->title);
        self::assertSame('https://cdn.test/one-piece.jpg', $results[0]->poster);
        self::assertSame('jkanime:one-piece-film-red', $results[1]->id);
    }

    public function test_get_anime_discovers_external_episode_id_from_html(): void
    {
        Http::fake([
            'jkanime.test/one-piece/' => Http::response($this->animeHtml(), 200, ['Content-Type' => 'text/html']),
        ]);

        $anime = app(JkAnimeProvider::class)->getAnime('jkanime:one-piece');

        self::assertNotNull($anime);
        self::assertSame('One Piece', $anime->title);
        self::assertSame('201', $anime->metadata?->externalIds['jkanime_id'] ?? null);
    }

    public function test_get_episodes_posts_to_discovered_ajax_endpoint(): void
    {
        Http::fake([
            'jkanime.test/one-piece/' => Http::response($this->animeHtml(), 200, ['Content-Type' => 'text/html']),
            'jkanime.test/ajax/episodes/201/74' => Http::response(['html' => $this->episodesHtml()], 200, ['Content-Type' => 'application/json']),
        ]);

        $episodes = app(JkAnimeProvider::class)->getEpisodes('one-piece', 74);

        self::assertCount(2, $episodes);
        self::assertSame(1175, $episodes[0]->number);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && (string) $request->url() === 'https://jkanime.test/ajax/episodes/201/74'
            && $request->hasHeader('X-Requested-With', 'XMLHttpRequest'));
    }

    public function test_get_episodes_parses_current_paginated_json_payload(): void
    {
        Http::fake([
            'jkanime.test/one-piece/' => Http::response($this->animeHtml(), 200, ['Content-Type' => 'text/html']),
            'jkanime.test/ajax/episodes/201/1' => Http::response([
                'current_page' => 1,
                'data' => [
                    ['id' => 4989, 'number' => 1, 'title' => 'One Piece 1', 'image' => 'jkvideo_1.jpg'],
                    ['id' => 4990, 'number' => 2, 'title' => 'One Piece 2', 'image' => 'jkvideo_2.jpg'],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $episodes = app(JkAnimeProvider::class)->getEpisodes('one-piece');

        self::assertCount(2, $episodes);
        self::assertSame(1, $episodes[0]->number);
        self::assertSame('One Piece 1', $episodes[0]->title);
        self::assertNull($episodes[0]->thumbnail);
    }

    public function test_get_episode_returns_embed_servers_without_resolving_player_tokens(): void
    {
        Http::fake([
            'jkanime.test/one-piece/1175' => Http::response($this->episodeHtml(), 200, ['Content-Type' => 'text/html']),
        ]);

        $episode = app(JkAnimeProvider::class)->getEpisode('one-piece', 1175);

        self::assertNotNull($episode);
        self::assertSame('One Piece 1175', $episode->title);
        self::assertContainsOnlyInstancesOf(Server::class, $episode->servers);
        self::assertSame('embed', $episode->servers[0]->type);
    }

    public function test_get_stream_resolves_allowed_direct_source_from_embed_player(): void
    {
        config(['anime.providers.jkanime.stream_allowed_hosts' => ['jkanime.test', 'nika.playmudos.test']]);

        Http::fake([
            'jkanime.test/one-piece/1175' => Http::response($this->episodeHtml(), 200, ['Content-Type' => 'text/html']),
            'jkanime.test/jkplayer/*' => Http::response($this->playerHtml(), 200, ['Content-Type' => 'text/html']),
        ]);

        $stream = app(JkAnimeProvider::class)->getStream('one-piece', 1175, 'magi');

        self::assertNotNull($stream);
        self::assertSame('hls', $stream->type);
        self::assertSame('m3u8', $stream->format);
        self::assertSame('https://nika.playmudos.test/live/one-piece.m3u8?st=signed&e=999', $stream->url);
    }

    public function test_provider_rejects_base_url_outside_allowlist_before_http_request(): void
    {
        config(['anime.providers.jkanime.base_url' => 'https://169.254.169.254']);
        Http::fake();

        self::assertSame([], app(JkAnimeProvider::class)->search('one piece'));
        Http::assertNothingSent();
    }

    private function searchHtml(): string
    {
        return <<<'HTML'
<html><body>
<a href="https://jkanime.test/one-piece/">
    <img src="https://cdn.test/one-piece.jpg" alt="One Piece">
    <h5>One Piece</h5>
</a>
</body></html>
HTML;
    }

    private function searchHtmlWithNavigation(): string
    {
        return <<<'HTML'
<html><body>
<nav>
    <a href="https://jkanime.test/notificaciones">Notificaciones</a>
    <a href="https://jkanime.test/favoritos">Favoritos</a>
</nav>
<a href="https://jkanime.test/one-punch-man/">
    <img src="https://cdn.test/one-punch-man.jpg" alt="One Punch Man">
    <h5>One Punch Man</h5>
</a>
</body></html>
HTML;
    }

    private function currentSearchCardHtml(): string
    {
        return <<<'HTML'
<html><body>
<div class="anime__page__content">
    <div class="anime__item">
        <a href="https://jkanime.test/one-piece/">
            <div class="g-0 anime__item__pic set-bg" data-setbg="https://cdn.test/one-piece.jpg"></div>
        </a>
        <div class="anime__item__text">
            <ul><li>En emision</li></ul>
            <h5><a href="https://jkanime.test/one-piece/">One Piece</a></h5>
        </div>
    </div>
    <div class="anime__item">
        <a href="https://jkanime.test/one-piece-film-red/">
            <div class="g-0 anime__item__pic set-bg" data-setbg="https://cdn.test/one-piece-film-red.jpg"></div>
        </a>
        <div class="anime__item__text">
            <ul><li>Concluido</li></ul>
            <h5><a href="https://jkanime.test/one-piece-film-red/">One Piece Film: Red</a></h5>
        </div>
    </div>
</div>
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
<meta property="og:image" content="https://cdn.test/one-piece.jpg"/>
</head><body>
<li><span>Episodios:</span> 1175</li>
<div data-status="currently"></div>
<script>$.ajax({ type: "POST", url: "https://jkanime.test/ajax/episodes/201/"+pag });</script>
</body></html>
HTML;
    }

    private function episodesHtml(): string
    {
        return <<<'HTML'
<a href="https://jkanime.test/one-piece/1175"><img src="https://cdn.test/1175.jpg">Episodio 1175</a>
<a href="https://jkanime.test/one-piece/1174">Episodio 1174</a>
HTML;
    }

    private function episodeHtml(): string
    {
        return <<<'HTML'
<html><head><title>One Piece 1175 Sub Español Online gratis — JkAnime</title></head><body>
<a data-id="0">Desu</a>
<a data-id="1">Magi</a>
<script>
var video = [];
video[0] = '<iframe class="player_conte" src="https://jkanime.test/jkplayer/um?e=temp&t=temp&op=temp"></iframe>';
video[1] = '<iframe class="player_conte" src="https://jkanime.test/jkplayer/umv?e=temp&t=temp"></iframe>';
</script>
</body></html>
HTML;
    }

    private function playerHtml(): string
    {
        return <<<'HTML'
<html><body>
<script>
player.src({ src: "https://nika.playmudos.test/live/one-piece.m3u8?st=signed&e=999", type: "application/x-mpegURL" });
</script>
</body></html>
HTML;
    }
}
