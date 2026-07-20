<?php

namespace App\Services\Dni;

use App\Domain\Dni\Contracts\DniProviderInterface;
use App\Domain\Dni\Data\DniData;
use Illuminate\Contracts\Cache\Repository;

class DniLookupService
{
    private const NOT_FOUND = '__not_found__';

    public function __construct(private readonly DniProviderInterface $provider, private readonly Repository $cache) {}

    public function find(string $dni): ?DniData
    {
        $key = 'dni:lookup:'.$dni;
        $cached = $this->cache->get($key);
        if ($cached === self::NOT_FOUND) {
            return null;
        }
        if (is_array($cached)) {
            return DniData::fromArray($cached);
        }
        $result = $this->provider->find($dni);
        if ($result === null) {
            $this->cache->put($key, self::NOT_FOUND, (int) config('dni.not_found_cache_ttl'));

            return null;
        }
        $this->cache->put($key, $result->toArray(), (int) config('dni.cache_ttl'));

        return $result;
    }
}
