<?php

namespace App\Services\Dni;

use App\Domain\Dni\Data\DniData;
use Illuminate\Contracts\Cache\Repository;

class DniCacheService
{
    private const NOT_FOUND = '__not_found__';

    public function __construct(private readonly Repository $cache, private readonly DniSettingsService $settings) {}

    public function get(string $dni): ?DniData
    {
        $value = $this->cache->get($this->key($dni));

        return is_array($value) ? DniData::fromArray($value) : null;
    }

    public function put(DniData $data): void
    {
        $this->cache->put($this->key($data->dni), $data->toArray(), $this->settings->cacheTtl());
    }

    public function isNotFound(string $dni): bool
    {
        return $this->cache->get($this->key($dni)) === self::NOT_FOUND;
    }

    public function rememberNotFound(string $dni): void
    {
        $this->cache->put($this->key($dni), self::NOT_FOUND, $this->settings->notFoundCacheTtl());
    }

    public function clearAll(): void
    {
        $this->cache->increment('dni:cache:version');
    }

    private function key(string $dni): string
    {
        $version = (int) $this->cache->get('dni:cache:version', 1);

        return "dni:v{$version}:lookup:{$dni}";
    }
}
