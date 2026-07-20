<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class ApiDocumentationSettingsService
{
    private const KEY = 'api_documentation.public';

    public function isPublic(): bool
    {
        $value = Cache::remember('settings:'.self::KEY, 300, fn () => SystemSetting::query()->where('key', self::KEY)->value('value'));

        return $value === null
            ? (bool) config('api.docs_public')
            : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function save(bool $public): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['group' => 'api_documentation', 'value' => $public ? '1' : '0', 'is_public' => false, 'is_encrypted' => false],
        );
        Cache::forget('settings:'.self::KEY);
    }
}
