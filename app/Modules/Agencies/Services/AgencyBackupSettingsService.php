<?php

namespace App\Modules\Agencies\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class AgencyBackupSettingsService
{
    public function maximumBackups(): int
    {
        return $this->integer('maximum_backups', (int) config('agency_backups.maximum_backups'), 1, 100);
    }

    public function retentionDays(): int
    {
        return $this->integer('retention_days', (int) config('agency_backups.retention_days'), 1, 3650);
    }

    public function autoCleanup(): bool
    {
        return filter_var($this->value('auto_cleanup', config('agency_backups.auto_cleanup')), FILTER_VALIDATE_BOOL);
    }

    public function save(int $maximum, int $days, bool $cleanup): void
    {
        foreach (['maximum_backups' => $maximum, 'retention_days' => $days, 'auto_cleanup' => $cleanup ? '1' : '0'] as $key => $value) {
            SystemSetting::query()->updateOrCreate(['key' => 'agency_backups.'.$key], [
                'group' => 'agency_backups', 'value' => (string) $value, 'is_public' => false, 'is_encrypted' => false,
            ]);
            Cache::forget('settings:agency_backups.'.$key);
        }
    }

    private function integer(string $key, int $fallback, int $min, int $max): int
    {
        return max($min, min($max, (int) $this->value($key, $fallback)));
    }

    private function value(string $key, mixed $fallback): mixed
    {
        return Cache::remember('settings:agency_backups.'.$key, 300, fn () => SystemSetting::query()->where('key', 'agency_backups.'.$key)->value('value')) ?? $fallback;
    }
}
