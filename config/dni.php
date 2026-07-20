<?php

return [
    'cache_ttl' => (int) env('DNI_CACHE_TTL', 86400),
    'not_found_cache_ttl' => (int) env('DNI_NOT_FOUND_CACHE_TTL', 300),
    'persist_external_results' => (bool) env('DNI_PERSIST_EXTERNAL_RESULTS', true),
    'refresh_after_days' => (int) env('DNI_REFRESH_AFTER_DAYS', 30),
    'rate_limit_per_minute' => (int) env('DNI_API_RATE_LIMIT_PER_MINUTE', 30),

    'perudevs' => [
        'enabled' => (bool) env('DNI_PERUDEVS_ENABLED', false),
        'base_url' => env('DNI_PERUDEVS_BASE_URL', 'https://api.perudevs.com/api/v1/dni/complete'),
        'api_key' => env('DNI_PERUDEVS_API_KEY'),
        'timeout_seconds' => (int) env('DNI_PERUDEVS_TIMEOUT', 10),
        'retry_times' => (int) env('DNI_PERUDEVS_RETRIES', 2),
    ],
];
