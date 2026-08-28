<?php

namespace App\Services\Anime\Providers;

use App\Services\Anime\Cache\ProviderCacheRepository;
use App\Services\Anime\Contracts\AnimeProviderInterface;
use App\Services\Anime\Data\Anime;
use App\Services\Anime\Data\Episode;
use App\Services\Anime\Data\Metadata;
use App\Services\Anime\Data\Stream;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class AniListProvider implements AnimeProviderInterface
{
    private const SEARCH_QUERY = <<<'GRAPHQL'
query CodeREDAnimeSearch($search: String!, $page: Int!, $perPage: Int!) {
  Page(page: $page, perPage: $perPage) {
    media(search: $search, type: ANIME) {
      id
      title { romaji english native userPreferred }
      synonyms
      description(asHtml: false)
      coverImage { large medium color }
      bannerImage
      genres
      season
      seasonYear
      status
      episodes
      studios(isMain: true) { nodes { id name } }
      relations {
        edges {
          relationType
          node {
            id
            type
            format
            title { romaji english native userPreferred }
          }
        }
      }
      characters(page: 1, perPage: 10) {
        edges {
          role
          node {
            id
            name { full native }
            image { medium }
          }
        }
      }
    }
  }
}
GRAPHQL;

    public function __construct(private readonly ProviderCacheRepository $cache) {}

    private const MEDIA_QUERY = <<<'GRAPHQL'
query CodeREDAnimeMedia($id: Int!) {
  Media(id: $id, type: ANIME) {
    id
    title { romaji english native userPreferred }
    synonyms
    description(asHtml: false)
    coverImage { large medium color }
    bannerImage
    genres
    season
    seasonYear
    status
    episodes
    studios(isMain: true) { nodes { id name } }
    relations {
      edges {
        relationType
        node {
          id
          type
          format
          title { romaji english native userPreferred }
        }
      }
    }
    characters(page: 1, perPage: 10) {
      edges {
        role
        node {
          id
          name { full native }
          image { medium }
        }
      }
    }
  }
}
GRAPHQL;

    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '' || ! $this->isEnabled()) {
            return [];
        }

        return $this->remember('search', hash('sha256', Str::lower($query)), (int) config('anime.cache.search_ttl'), function () use ($query): array {
            $payload = $this->request('search', [], [
                'query' => self::SEARCH_QUERY,
                'variables' => [
                    'search' => $query,
                    'page' => 1,
                    'perPage' => max((int) config('anime.providers.anilist.search_limit', 10), 1),
                ],
            ]);

            return collect(Arr::get($payload, 'data.Page.media', []))
                ->filter(fn ($media): bool => is_array($media))
                ->map(fn (array $media): Anime => $this->toAnime($media))
                ->values()
                ->all();
        });
    }

    public function getAnime(string $id): ?Anime
    {
        $anilistId = $this->anilistId($id);
        if ($anilistId === null || ! $this->isEnabled()) {
            return null;
        }

        return $this->remember('anime', (string) $anilistId, (int) config('anime.cache.metadata_ttl'), function () use ($anilistId): ?Anime {
            $payload = $this->request('get_anime', ['anilist_id' => $anilistId], [
                'query' => self::MEDIA_QUERY,
                'variables' => ['id' => $anilistId],
            ]);

            $media = Arr::get($payload, 'data.Media');

            return is_array($media) ? $this->toAnime($media) : null;
        });
    }

    public function getEpisodes(string $animeId, ?int $page = null): array
    {
        return [];
    }

    public function getEpisode(string $animeId, int $episode): ?Episode
    {
        return null;
    }

    public function getServers(string $animeId, int $episode): array
    {
        return [];
    }

    public function getStream(string $animeId, int $episode, string $server): ?Stream
    {
        return null;
    }

    public function isEnabled(): bool
    {
        return (bool) config('anime.enabled') && (bool) config('anime.providers.anilist.enabled');
    }

    private function request(string $operation, array $context, array $payload): array
    {
        $started = hrtime(true);

        try {
            $response = $this->http()->post($this->baseUrl(), $payload);
            $this->log($operation, $context, 'success', $started, $response->status());

            if (! $response->successful()) {
                return [];
            }

            return $response->json();
        } catch (ConnectionException $exception) {
            $this->log($operation, $context, 'timeout', $started, null, $exception);

            return [];
        } catch (Throwable $exception) {
            $this->log($operation, $context, 'failed', $started, null, $exception);

            return [];
        }
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withUserAgent((string) config('anime.providers.anilist.user_agent'))
            ->connectTimeout(max((int) config('anime.connect_timeout'), 1))
            ->timeout(max((int) config('anime.request_timeout'), 1))
            ->retry(1, 500, throw: false);
    }

    private function remember(string $bucket, string $key, int $ttl, callable $callback): mixed
    {
        return $this->cache->remember('anilist', $bucket, $key, $ttl, $callback);
    }

    private function anilistId(string $id): ?int
    {
        $raw = Str::after($id, 'anilist:');
        if (preg_match('/^[1-9][0-9]{0,9}$/', $raw) !== 1) {
            return null;
        }

        return (int) $raw;
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('anime.providers.anilist.base_url'), '/');
        if (! $this->isAllowedProviderUrl($baseUrl)) {
            throw new InvalidArgumentException('ANILIST_BASE_URL fuera de allowlist.');
        }

        return $baseUrl;
    }

    private function isAllowedProviderUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $allowedHosts = config('anime.providers.anilist.allowed_hosts', []);

        return $scheme === 'https' && is_string($host) && in_array($host, $allowedHosts, true);
    }

    private function toAnime(array $media): Anime
    {
        $id = (int) ($media['id'] ?? 0);
        $titles = array_filter([
            'romaji' => Arr::get($media, 'title.romaji'),
            'english' => Arr::get($media, 'title.english'),
            'native' => Arr::get($media, 'title.native'),
            'user_preferred' => Arr::get($media, 'title.userPreferred'),
        ]);
        $title = (string) ($titles['user_preferred'] ?? $titles['romaji'] ?? $titles['english'] ?? $titles['native'] ?? 'Anime '.$id);
        $description = $this->cleanDescription(Arr::get($media, 'description'));
        $genres = array_values(array_filter(Arr::wrap($media['genres'] ?? []), 'is_string'));
        $year = is_numeric($media['seasonYear'] ?? null) ? (int) $media['seasonYear'] : null;
        $episodes = is_numeric($media['episodes'] ?? null) ? (int) $media['episodes'] : null;
        $status = is_string($media['status'] ?? null) ? Str::lower($media['status']) : null;
        $season = is_string($media['season'] ?? null) ? Str::lower($media['season']) : null;
        $synonyms = array_values(array_filter(Arr::wrap($media['synonyms'] ?? []), 'is_string'));

        $metadata = new Metadata(
            titles: $titles,
            synonyms: $synonyms,
            genres: $genres,
            studios: $this->studios($media),
            relations: $this->relations($media),
            characters: $this->characters($media),
            externalIds: ['anilist_id' => $id],
            description: $description,
            season: $season,
            status: $status,
            year: $year,
            episodes: $episodes,
        );

        return new Anime(
            id: 'anilist:'.$id,
            slug: Str::slug($titles['romaji'] ?? $title),
            title: $title,
            titles: $titles,
            genres: $genres,
            year: $year,
            description: $description,
            poster: Arr::get($media, 'coverImage.large') ?: Arr::get($media, 'coverImage.medium'),
            banner: is_string($media['bannerImage'] ?? null) ? $media['bannerImage'] : null,
            episodes: $episodes,
            status: $status,
            metadata: $metadata,
        );
    }

    private function cleanDescription(mixed $description): ?string
    {
        if (! is_string($description) || trim($description) === '') {
            return null;
        }

        return trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<br>', '<br />', '<br/>'], ' ', $description))) ?? '');
    }

    private function studios(array $media): array
    {
        return collect(Arr::get($media, 'studios.nodes', []))
            ->filter(fn ($studio): bool => is_array($studio))
            ->map(fn (array $studio): array => [
                'id' => $studio['id'] ?? null,
                'name' => $studio['name'] ?? null,
            ])
            ->filter(fn (array $studio): bool => is_int($studio['id']) && is_string($studio['name']))
            ->values()
            ->all();
    }

    private function relations(array $media): array
    {
        return collect(Arr::get($media, 'relations.edges', []))
            ->filter(fn ($edge): bool => is_array($edge) && is_array($edge['node'] ?? null))
            ->map(fn (array $edge): array => [
                'relation_type' => is_string($edge['relationType'] ?? null) ? Str::lower($edge['relationType']) : null,
                'anilist_id' => $edge['node']['id'] ?? null,
                'type' => $edge['node']['type'] ?? null,
                'format' => $edge['node']['format'] ?? null,
                'title' => Arr::get($edge, 'node.title.userPreferred') ?: Arr::get($edge, 'node.title.romaji'),
                'titles' => array_filter([
                    'romaji' => Arr::get($edge, 'node.title.romaji'),
                    'english' => Arr::get($edge, 'node.title.english'),
                    'native' => Arr::get($edge, 'node.title.native'),
                    'user_preferred' => Arr::get($edge, 'node.title.userPreferred'),
                ]),
            ])
            ->filter(fn (array $relation): bool => is_int($relation['anilist_id']))
            ->values()
            ->all();
    }

    private function characters(array $media): array
    {
        return collect(Arr::get($media, 'characters.edges', []))
            ->filter(fn ($edge): bool => is_array($edge) && is_array($edge['node'] ?? null))
            ->map(fn (array $edge): array => [
                'role' => is_string($edge['role'] ?? null) ? Str::lower($edge['role']) : null,
                'anilist_id' => $edge['node']['id'] ?? null,
                'name' => Arr::get($edge, 'node.name.full'),
                'native_name' => Arr::get($edge, 'node.name.native'),
                'image' => Arr::get($edge, 'node.image.medium'),
            ])
            ->filter(fn (array $character): bool => is_int($character['anilist_id']) && is_string($character['name']))
            ->values()
            ->all();
    }

    private function log(string $operation, array $context, string $status, int $started, ?int $statusCode = null, ?Throwable $exception = null): void
    {
        Log::info('anime.provider', array_filter([
            'provider' => 'anilist',
            'operation' => $operation,
            'anilist_id' => $context['anilist_id'] ?? null,
            'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'status' => $status,
            'status_code' => $statusCode,
            'error' => $exception?->getMessage(),
        ], static fn ($value): bool => $value !== null && $value !== ''));
    }
}
