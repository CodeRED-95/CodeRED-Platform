<?php

namespace App\Services\Anime\Providers;

use App\Services\Anime\Data\Anime;
use App\Services\Anime\Data\Episode;
use App\Services\Anime\Data\Metadata;
use App\Services\Anime\Data\Server;
use DOMDocument;
use DOMElement;
use DOMXPath;

final class JkAnimeHtmlParser
{
    public function parseSearch(string $html, string $baseUrl): array
    {
        $fromJson = $this->parseAnimesVariable($html);
        if ($fromJson !== []) {
            return $fromJson;
        }

        [$dom, $xpath] = $this->document($html);
        if ($dom === null || $xpath === null) {
            return [];
        }

        $results = [];
        foreach ($xpath->query('//a[@href]') ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = $this->absoluteUrl($baseUrl, $link->getAttribute('href'));
            $slug = $this->slugFromUrl($href, $baseUrl);
            if ($slug === null || isset($results[$slug])) {
                continue;
            }

            $title = trim($link->getAttribute('title') ?: $link->textContent);
            $image = $this->firstImage($link);
            if ($title === '' && $image instanceof DOMElement) {
                $title = trim($image->getAttribute('alt'));
            }
            if ($title === '' || $this->isNavigationSlug($slug)) {
                continue;
            }

            $results[$slug] = new Anime(
                id: 'jkanime:'.$slug,
                slug: $slug,
                title: $this->cleanText($title),
                titles: ['romaji' => $this->cleanText($title)],
                poster: $image instanceof DOMElement ? $this->absoluteUrl($baseUrl, $image->getAttribute('src')) : null,
                metadata: new Metadata(externalIds: ['jkanime_slug' => $slug]),
            );
        }

        return array_values($results);
    }

    public function parseAnime(string $html, string $slug, string $baseUrl): ?Anime
    {
        $title = $this->meta($html, 'og:title') ?? $this->titleTag($html);
        $title = $title !== null ? preg_replace('/\s+-\s+anime.*$/i', '', $title) : null;
        $title = $this->cleanText((string) $title);
        if ($title === '') {
            return null;
        }

        $description = $this->cleanText((string) ($this->meta($html, 'description') ?? '')) ?: null;
        $poster = $this->meta($html, 'og:image');
        $episodes = $this->firstIntAfterLabel($html, 'Episodios');
        $status = $this->status($html);
        $externalId = $this->externalAnimeId($html);

        return new Anime(
            id: 'jkanime:'.$slug,
            slug: $slug,
            title: $title,
            titles: ['romaji' => $title],
            description: $description,
            poster: $poster,
            episodes: $episodes,
            status: $status,
            metadata: new Metadata(
                titles: ['romaji' => $title],
                externalIds: array_filter([
                    'jkanime_id' => $externalId,
                    'jkanime_slug' => $slug,
                ]),
                description: $description,
                status: $status,
                episodes: $episodes,
            ),
        );
    }

    public function parseEpisodes(string $body, string $slug, ?int $page = null): array
    {
        $payload = json_decode($body, true);
        $html = $body;

        if (is_array($payload)) {
            $html = $this->firstHtmlString($payload) ?? '';
        }

        [$dom, $xpath] = $this->document($html);
        if ($dom === null || $xpath === null) {
            return [];
        }

        $episodes = [];
        foreach ($xpath->query('//a[@href]') ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = $link->getAttribute('href');
            if (! preg_match('~/(?:'.preg_quote($slug, '~').')/(\d+)/?~i', $href, $matches)) {
                continue;
            }

            $number = (int) $matches[1];
            $title = $this->cleanText($link->textContent) ?: 'Episodio '.$number;
            $image = $this->firstImage($link);
            $episodes[$number] = new Episode(
                id: sprintf('jkanime:%s:%d', $slug, $number),
                animeId: 'jkanime:'.$slug,
                number: $number,
                title: $title,
                thumbnail: $image instanceof DOMElement ? $image->getAttribute('src') : null,
            );
        }

        krsort($episodes);

        return array_values($episodes);
    }

    public function parseEpisode(string $html, string $slug, int $episode): Episode
    {
        $title = $this->titleTag($html);
        if ($title !== null) {
            $title = preg_replace('/\s+Sub\s+Español.*$/iu', '', $title);
            $title = $this->cleanText($title);
        }

        return new Episode(
            id: sprintf('jkanime:%s:%d', $slug, $episode),
            animeId: 'jkanime:'.$slug,
            number: $episode,
            title: $title ?: 'Episodio '.$episode,
            servers: $this->parseServers($html, $slug, $episode),
        );
    }

    public function parseServers(string $html, string $slug, int $episode): array
    {
        $servers = [];
        $names = $this->videoNames($html);

        foreach ($this->videoIframes($html) as $index => $url) {
            $name = $names[$index] ?? $this->serverNameFromUrl($url, $index);
            $servers[] = new Server(
                id: sprintf('jkanime:%s:%d:%s', $slug, $episode, str($name)->slug()->toString() ?: 'server-'.$index),
                name: $name,
                type: $this->isDirectStreamUrl($url) ? 'stream' : 'embed',
                url: $url,
                provider: 'jkanime',
            );
        }

        return $servers;
    }

    public function externalAnimeId(string $html): ?string
    {
        if (preg_match('~/ajax/episodes/(\d+)/~', $html, $matches)) {
            return $matches[1];
        }

        if (preg_match('/data-anime=["\'](\d+)["\']/i', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function csrfToken(string $html): ?string
    {
        if (preg_match('/<meta[^>]+name=["\']csrf-token["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function parseAnimesVariable(string $html): array
    {
        if (! preg_match('/var\s+animes\s*=\s*(\{.*?\});/s', $html, $matches)) {
            return [];
        }

        $payload = json_decode($matches[1], true);
        $items = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return array_values(array_filter(array_map(function ($item): ?Anime {
            if (! is_array($item)) {
                return null;
            }

            $slug = $this->cleanSlug((string) ($item['slug'] ?? ''));
            $title = $this->cleanText((string) ($item['title'] ?? ''));
            if ($slug === null || $title === '') {
                return null;
            }

            return new Anime(
                id: 'jkanime:'.$slug,
                slug: $slug,
                title: $title,
                titles: ['romaji' => $title],
                description: $this->nullableText($item['synopsis'] ?? null),
                poster: $this->nullableText($item['image'] ?? null),
                status: $this->nullableText($item['status'] ?? null),
                metadata: new Metadata(
                    titles: ['romaji' => $title],
                    externalIds: array_filter([
                        'jkanime_id' => isset($item['id']) ? (string) $item['id'] : null,
                        'jkanime_slug' => $slug,
                    ]),
                    description: $this->nullableText($item['synopsis'] ?? null),
                    status: $this->nullableText($item['status'] ?? null),
                ),
            );
        }, $items)));
    }

    private function firstHtmlString(array $payload): ?string
    {
        foreach ($payload as $value) {
            if (is_string($value) && str_contains($value, '<')) {
                return $value;
            }
            if (is_array($value) && ($nested = $this->firstHtmlString($value)) !== null) {
                return $nested;
            }
        }

        return null;
    }

    private function videoIframes(string $html): array
    {
        preg_match_all('/video\[(\d+)\]\s*=\s*[\'"](.+?)[\'"]\s*;/s', $html, $matches, PREG_SET_ORDER);

        $urls = [];
        foreach ($matches as $match) {
            if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', html_entity_decode($match[2]), $iframe)) {
                $urls[(int) $match[1]] = $iframe[1];
            }
        }

        ksort($urls);

        return array_values($urls);
    }

    private function videoNames(string $html): array
    {
        preg_match_all('/data-id=["\'](\d+)["\'][^>]*>([^<]+)<\/a>/i', $html, $matches, PREG_SET_ORDER);

        $names = [];
        foreach ($matches as $match) {
            $names[(int) $match[1]] = $this->cleanText($match[2]);
        }

        return $names;
    }

    private function serverNameFromUrl(string $url, int $index): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $suffix = is_string($path) ? trim(basename($path)) : '';

        return $suffix !== '' ? strtoupper($suffix) : 'Opcion '.($index + 1);
    }

    private function isDirectStreamUrl(string $url): bool
    {
        return preg_match('/\.(m3u8?|mp4)(?:[?#]|$)/i', $url) === 1;
    }

    private function meta(string $html, string $name): ?string
    {
        $attribute = str_contains($name, ':') ? 'property' : 'name';
        if (preg_match('/<meta[^>]+'.$attribute.'=["\']'.preg_quote($name, '/').'["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $this->cleanText(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return null;
    }

    private function titleTag(string $html): ?string
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            return $this->cleanText(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return null;
    }

    private function firstIntAfterLabel(string $html, string $label): ?int
    {
        if (preg_match('/<span>\s*'.preg_quote($label, '/').'\s*:\s*<\/span>\s*(\d+)/iu', $html, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function status(string $html): ?string
    {
        if (preg_match('/data-status=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $this->cleanText($matches[1]);
        }

        return null;
    }

    private function firstImage(DOMElement $element): ?DOMElement
    {
        foreach ($element->getElementsByTagName('img') as $image) {
            return $image;
        }

        return null;
    }

    private function slugFromUrl(string $url, string $baseUrl): ?string
    {
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $host = parse_url($url, PHP_URL_HOST);
        if ($baseHost === null || $host === null || ! hash_equals($baseHost, $host)) {
            return null;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '' || str_contains($path, '/')) {
            return null;
        }

        return $this->cleanSlug($path);
    }

    private function cleanSlug(string $slug): ?string
    {
        $slug = trim($slug, " \t\n\r\0\x0B/");

        return preg_match('/^[a-z0-9][a-z0-9-]{0,120}$/', $slug) === 1 ? $slug : null;
    }

    private function isNavigationSlug(string $slug): bool
    {
        return in_array($slug, ['buscar', 'directorio', 'horario', 'comunidad', 'top', 'estrenos', 'aleatorio', 'aplicacion'], true);
    }

    private function absoluteUrl(string $baseUrl, string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return parse_url($baseUrl, PHP_URL_SCHEME).'://'.ltrim($url, '/');
        }

        return rtrim($baseUrl, '/').'/'.ltrim($url, '/');
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function nullableText(mixed $value): ?string
    {
        return is_scalar($value) && $this->cleanText((string) $value) !== '' ? $this->cleanText((string) $value) : null;
    }

    private function document(string $html): array
    {
        if (trim($html) === '') {
            return [null, null];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? [$dom, new DOMXPath($dom)] : [null, null];
    }
}
