<?php

namespace App\Services\Dni;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class DniSettingsService
{
    private const GROUP = 'dni_perudevs';

    public function enabled(): bool
    {
        return $this->boolean('enabled', (bool) config('dni.perudevs.enabled'));
    }

    public function baseUrl(): string
    {
        return $this->string('base_url', (string) config('dni.perudevs.base_url'));
    }

    public function endpointPath(): string
    {
        return (string) config('dni.perudevs.endpoint_path');
    }

    public function apiToken(): ?string
    {
        $setting = $this->setting('api_token');
        if ($setting === null || blank($setting->value)) {
            return filled(config('dni.perudevs.api_token')) ? (string) config('dni.perudevs.api_token') : null;
        }

        return $setting->is_encrypted ? Crypt::decryptString((string) $setting->value) : null;
    }

    public function hasApiToken(): bool
    {
        return filled($this->apiToken());
    }

    public function maskedToken(): ?string
    {
        if (! $this->hasApiToken()) {
            return null;
        }

        return '••••••••••••••••'.substr((string) $this->apiToken(), -4);
    }

    public function timeout(): int
    {
        return $this->integer('timeout_seconds', (int) config('dni.perudevs.timeout_seconds'), 1, 60);
    }

    public function retries(): int
    {
        return $this->integer('retry_times', (int) config('dni.perudevs.retry_times'), 0, 5);
    }

    public function cacheTtl(): int
    {
        return $this->integer('cache_ttl_seconds', (int) config('dni.cache_ttl'), 60, 604800);
    }

    public function notFoundCacheTtl(): int
    {
        return $this->integer('not_found_cache_ttl_seconds', (int) config('dni.not_found_cache_ttl'), 30, 86400);
    }

    public function persistResults(): bool
    {
        return $this->boolean('persist_results', (bool) config('dni.persist_external_results'));
    }

    public function refreshAfterDays(): int
    {
        return $this->integer('refresh_after_days', (int) config('dni.refresh_after_days'), 1, 365);
    }

    public function save(array $values, ?string $newToken = null): void
    {
        foreach (['enabled', 'base_url', 'timeout_seconds', 'retry_times', 'cache_ttl_seconds', 'not_found_cache_ttl_seconds', 'persist_results', 'refresh_after_days'] as $key) {
            SystemSetting::query()->updateOrCreate(['key' => self::GROUP.'.'.$key], ['group' => self::GROUP, 'value' => is_bool($values[$key]) ? ($values[$key] ? '1' : '0') : (string) $values[$key], 'is_public' => false, 'is_encrypted' => false]);
        }
        if (filled($newToken)) {
            SystemSetting::query()->updateOrCreate(['key' => self::GROUP.'.api_token'], ['group' => self::GROUP, 'value' => Crypt::encryptString(trim($newToken)), 'is_public' => false, 'is_encrypted' => true]);
        }
        $this->forget();
    }

    public function deleteToken(): void
    {
        SystemSetting::query()->where('key', self::GROUP.'.api_token')->delete();
        $this->forget();
    }

    public function forget(): void
    {
        foreach (['enabled', 'base_url', 'api_token', 'timeout_seconds', 'retry_times', 'cache_ttl_seconds', 'not_found_cache_ttl_seconds', 'persist_results', 'refresh_after_days'] as $key) {
            Cache::forget($this->cacheKey($key));
        }
    }

    private function setting(string $key): ?SystemSetting
    {
        return Cache::remember($this->cacheKey($key), 300, fn () => SystemSetting::query()->where('key', self::GROUP.'.'.$key)->first());
    }

    private function string(string $key, string $fallback): string
    {
        $setting = $this->setting($key);

        return $setting === null ? $fallback : (string) $setting->value;
    }

    private function boolean(string $key, bool $fallback): bool
    {
        $value = $this->setting($key)?->value;

        return $value === null ? $fallback : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function integer(string $key, int $fallback, int $min, int $max): int
    {
        $value = $this->setting($key)?->value;

        return min(max(is_numeric($value) ? (int) $value : $fallback, $min), $max);
    }

    private function cacheKey(string $key): string
    {
        return 'settings:dni_perudevs:'.$key;
    }
}
