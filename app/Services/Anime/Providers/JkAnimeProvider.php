<?php

namespace App\Services\Anime\Providers;

use App\Services\Anime\Cache\ProviderCacheRepository;
use App\Services\Anime\Contracts\AnimeProviderInterface;
use App\Services\Anime\Data\Anime;
use App\Services\Anime\Data\Episode;
use App\Services\Anime\Data\Server;
use App\Services\Anime\Data\Stream;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class JkAnimeProvider implements AnimeProviderInterface
{
    public function __construct(
        private readonly JkAnimeHtmlParser $parser,
        private readonly ProviderCacheRepository $cache,
    ) {}

    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '' || ! $this->isEnabled()) {
            return [];
        }

        return $this->remember('search', hash('sha256', Str::lower($query)), (int) config('anime.cache.search_ttl'), function () use ($query): array {
            $response = $this->request('search', ['query' => $query], fn (PendingRequest $http) => $http->get($this->url('/buscar'), ['q' => $query]));
            if ($response === null || ! $response->successful()) {
                return [];
            }

            return $this->parser->parseSearch($response->body(), $this->baseUrl());
        });
    }

    public function getAnime(string $id): ?Anime
    {
        $slug = $this->slug($id);
        if ($slug === null || ! $this->isEnabled()) {
            return null;
        }

        return $this->remember('anime', $slug, (int) config('anime.cache.metadata_ttl'), function () use ($slug): ?Anime {
            $response = $this->request('get_anime', ['anime_id' => $slug], fn (PendingRequest $http) => $http->get($this->url('/'.$slug.'/')));
            if ($response === null || $response->status() === 404 || ! $response->successful()) {
                return null;
            }

            return $this->parser->parseAnime($response->body(), $slug, $this->baseUrl());
        });
    }

    public function getEpisodes(string $animeId, ?int $page = null): array
    {
        $slug = $this->slug($animeId);
        if ($slug === null || ! $this->isEnabled()) {
            return [];
        }

        $page = max((int) ($page ?? 1), 1);

        return $this->remember('episodes', $slug.':'.$page, (int) config('anime.cache.episodes_ttl'), function () use ($slug, $page): array {
            $animePage = $this->request('get_episode_index', ['anime_id' => $slug], fn (PendingRequest $http) => $http->get($this->url('/'.$slug.'/')));
            if ($animePage === null || ! $animePage->successful()) {
                return [];
            }

            $externalId = $this->parser->externalAnimeId($animePage->body());
            if ($externalId === null) {
                return [];
            }

            $csrf = $this->parser->csrfToken($animePage->body());
            $response = $this->request('get_episodes', ['anime_id' => $slug, 'page' => $page], function (PendingRequest $http) use ($externalId, $page, $slug, $csrf) {
                $request = $http->withHeaders(array_filter([
                    'Referer' => $this->url('/'.$slug.'/'),
                    'X-Requested-With' => 'XMLHttpRequest',
                    'X-CSRF-TOKEN' => $csrf,
                ]));

                return $request->post($this->url('/ajax/episodes/'.$externalId.'/'.$page));
            });

            if ($response === null || ! $response->successful()) {
                return [];
            }

            return $this->parser->parseEpisodes($response->body(), $slug, $page);
        });
    }

    public function getEpisode(string $animeId, int $episode): ?Episode
    {
        $slug = $this->slug($animeId);
        if ($slug === null || $episode < 1 || ! $this->isEnabled()) {
            return null;
        }

        return $this->remember('episode', $slug.':'.$episode, (int) config('anime.cache.metadata_ttl'), function () use ($slug, $episode): ?Episode {
            $response = $this->request('get_episode', ['anime_id' => $slug, 'episode' => $episode], fn (PendingRequest $http) => $http->get($this->url('/'.$slug.'/'.$episode)));
            if ($response === null || $response->status() === 404 || ! $response->successful()) {
                return null;
            }

            return $this->parser->parseEpisode($response->body(), $slug, $episode);
        });
    }

    public function getServers(string $animeId, int $episode): array
    {
        $slug = $this->slug($animeId);
        if ($slug === null || $episode < 1 || ! $this->isEnabled()) {
            return [];
        }

        return $this->remember('servers', $slug.':'.$episode, (int) config('anime.cache.servers_ttl'), function () use ($slug, $episode): array {
            $episodeData = $this->getEpisode($slug, $episode);

            return $episodeData instanceof Episode ? $episodeData->servers : [];
        });
    }

    public function getStream(string $animeId, int $episode, string $server): ?Stream
    {
        $servers = $this->getServers($animeId, $episode);
        $selected = collect($servers)->first(fn (Server $candidate): bool => $candidate->id === $server || Str::lower($candidate->name) === Str::lower($server));
        if (! $selected instanceof Server || $selected->type !== 'stream' || $selected->url === null) {
            return null;
        }

        if (! $this->isAllowedProviderUrl($selected->url) || ! $this->isDirectStreamUrl($selected->url)) {
            return null;
        }

        $format = Str::afterLast(parse_url($selected->url, PHP_URL_PATH) ?: '', '.');

        return new Stream(
            url: $selected->url,
            type: in_array($format, ['m3u8', 'm3u'], true) ? 'hls' : 'file',
            format: $format,
            headers: [],
        );
    }

    public function isEnabled(): bool
    {
        return (bool) config('anime.enabled') && (bool) config('anime.providers.jkanime.enabled');
    }

    private function request(string $operation, array $context, callable $callback): mixed
    {
        $started = hrtime(true);

        try {
            $response = $callback($this->http());
            $this->log($operation, $context, 'success', $started, method_exists($response, 'status') ? $response->status() : null);

            return $response;
        } catch (ConnectionException $exception) {
            $this->log($operation, $context, 'timeout', $started, null, $exception);

            return null;
        } catch (Throwable $exception) {
            $this->log($operation, $context, 'failed', $started, null, $exception);

            return null;
        }
    }

    private function http(): PendingRequest
    {
        return Http::accept('text/html,application/xhtml+xml,application/json')
            ->withUserAgent((string) config('anime.providers.jkanime.user_agent'))
            ->connectTimeout(max((int) config('anime.connect_timeout'), 1))
            ->timeout(max((int) config('anime.request_timeout'), 1))
            ->retry(1, 500, throw: false);
    }

    private function remember(string $bucket, string $key, int $ttl, callable $callback): mixed
    {
        return $this->cache->remember('jkanime', $bucket, $key, $ttl, $callback);
    }

    private function slug(string $id): ?string
    {
        $slug = Str::after($id, 'jkanime:');
        $slug = trim($slug, " \t\n\r\0\x0B/");

        return preg_match('/^[a-z0-9][a-z0-9-]{0,120}$/', $slug) === 1 ? $slug : null;
    }

    private function url(string $path): string
    {
        $url = rtrim($this->baseUrl(), '/').'/'.ltrim($path, '/');
        if (! $this->isAllowedProviderUrl($url)) {
            throw new InvalidArgumentException('JkAnime URL fuera de allowlist.');
        }

        return $url;
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('anime.providers.jkanime.base_url'), '/');
        if (! $this->isAllowedProviderUrl($baseUrl)) {
            throw new InvalidArgumentException('JKANIME_BASE_URL fuera de allowlist.');
        }

        return $baseUrl;
    }

    private function isAllowedProviderUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $allowedHosts = config('anime.providers.jkanime.allowed_hosts', []);

        return $scheme === 'https' && is_string($host) && in_array($host, $allowedHosts, true);
    }

    private function isDirectStreamUrl(string $url): bool
    {
        return preg_match('/\.(m3u8?|mp4)(?:[?#]|$)/i', $url) === 1;
    }

    private function log(string $operation, array $context, string $status, int $started, ?int $statusCode = null, ?Throwable $exception = null): void
    {
        Log::info('anime.provider', array_filter([
            'provider' => 'jkanime',
            'operation' => $operation,
            'anime_id' => $context['anime_id'] ?? null,
            'episode' => $context['episode'] ?? null,
            'page' => $context['page'] ?? null,
            'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'status' => $status,
            'status_code' => $statusCode,
            'error' => $exception?->getMessage(),
        ], static fn ($value): bool => $value !== null && $value !== ''));
    }
}
