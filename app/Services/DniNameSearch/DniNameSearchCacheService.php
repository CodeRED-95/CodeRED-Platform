<?php

declare(strict_types=1);

namespace App\Services\DniNameSearch;

use App\Domain\DniNameSearch\Data\DniNameMatch;
use Illuminate\Contracts\Cache\Repository;

final class DniNameSearchCacheService
{
    public function __construct(private readonly Repository $cache) {}

    /** @return list<DniNameMatch>|null */
    public function get(string $nombres, string $paterno, string $materno): ?array
    {
        $value = $this->cache->get($this->key($nombres, $paterno, $materno));
        if (! is_array($value)) {
            return null;
        }
        $result = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }
            $result[] = new DniNameMatch(
                (string) ($item['dni'] ?? ''),
                (string) ($item['nombres'] ?? ''),
                (string) ($item['apellido_paterno'] ?? ''),
                (string) ($item['apellido_materno'] ?? ''),
            );
        }

        return $result;
    }

    /** @param list<DniNameMatch> $matches */
    public function put(string $nombres, string $paterno, string $materno, array $matches): void
    {
        $ttl = max((int) config('dni.name_search.cache_ttl_seconds', 86400), 60);
        $this->cache->put($this->key($nombres, $paterno, $materno), array_map(fn (DniNameMatch $m) => $m->toArray(), $matches), $ttl);
    }

    private function key(string $nombres, string $paterno, string $materno): string
    {
        $normalized = implode('|', [mb_strtoupper(trim($nombres)), mb_strtoupper(trim($paterno)), mb_strtoupper(trim($materno))]);

        return 'dni-name-search:v1:'.hash('sha256', $normalized);
    }
}
