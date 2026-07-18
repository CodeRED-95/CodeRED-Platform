<?php

namespace App\Modules\Agencies\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class AgencyVersion
{
    public const CACHE_KEY = 'agencies:global-version';

    public static function current(): int
    {
        return (int) Cache::rememberForever(self::CACHE_KEY, function (): int {
            return (int) (DB::table('agencies')->max('data_version') ?? 0);
        });
    }

    public static function bump(): int
    {
        $version = (int) Cache::lock(self::CACHE_KEY.':lock', 10)->block(5, function (): int {
            $next = ((int) DB::table('agencies')->max('data_version') ?: 0) + 1;
            Cache::forever(self::CACHE_KEY, $next);

            return $next;
        });

        return $version;
    }
}
