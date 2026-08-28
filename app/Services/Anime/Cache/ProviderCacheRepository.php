<?php

namespace App\Services\Anime\Cache;

use App\Services\Anime\Models\ProviderCache;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ProviderCacheRepository
{
    public function remember(string $provider, string $bucket, string $key, int $ttl, callable $callback): mixed
    {
        if (! (bool) config('anime.cache.enabled')) {
            return $callback();
        }

        $ttl = max($ttl, 1);
        $cacheKey = $this->cacheKey($provider, $bucket, $key);

        return $this->store()->remember($cacheKey, $ttl, function () use ($provider, $bucket, $key, $ttl, $callback): mixed {
            $value = $callback();
            $this->persistSnapshot($provider, $bucket, $key, $value, $ttl);

            return $value;
        });
    }

    public function forget(string $provider, string $bucket, string $key): bool
    {
        return $this->store()->forget($this->cacheKey($provider, $bucket, $key));
    }

    public function cacheKey(string $provider, string $bucket, string $key): string
    {
        return 'anime:'.$provider.':'.$bucket.':'.$key;
    }

    private function store(): Repository
    {
        $store = config('anime.cache.store');

        return is_string($store) && $store !== '' ? Cache::store($store) : Cache::store();
    }

    private function persistSnapshot(string $provider, string $bucket, string $key, mixed $value, int $ttl): void
    {
        if (! (bool) config('anime.cache.mirror_database')) {
            return;
        }

        try {
            if (! Schema::hasTable('provider_cache')) {
                return;
            }

            ProviderCache::query()->updateOrCreate(
                [
                    'provider' => $provider,
                    'bucket' => $bucket,
                    'cache_key' => $key,
                ],
                [
                    'payload' => $this->toPayload($value),
                    'status' => 'fresh',
                    'expires_at' => now()->addSeconds($ttl),
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('anime.cache.snapshot_failed', [
                'provider' => $provider,
                'bucket' => $bucket,
                'duration_ttl' => $ttl,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function toPayload(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->payloadValue($item), $value);
        }

        return ['value' => $this->payloadValue($value)];
    }

    private function payloadValue(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            return [
                'type' => $value::class,
                'data' => $value->toArray(),
            ];
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->payloadValue($item), $value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return ['type' => get_debug_type($value)];
    }
}
