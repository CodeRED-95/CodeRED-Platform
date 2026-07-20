<?php

return [
    'cache_ttl' => (int) env('DNI_CACHE_TTL', 86400),
    'not_found_cache_ttl' => (int) env('DNI_NOT_FOUND_CACHE_TTL', 300),
    'persist_external_results' => (bool) env('DNI_PERSIST_EXTERNAL_RESULTS', true),
    'refresh_after_days' => (int) env('DNI_REFRESH_AFTER_DAYS', 30),
    'rate_limit_per_minute' => (int) env('DNI_RATE_LIMIT_PER_MINUTE', 30),

    'perudevs' => [
        'enabled' => (bool) env('DNIPERUDEVS_ENABLED', false),
        'base_url' => env('DNIPERUDEVS_BASE_URL', 'https://service.fitcoders.com/enty'),
        'endpoint_path' => env('DNIPERUDEVS_DNI_PATH', '/v1/entity/dni/complete'),
        'api_token' => env('DNIPERUDEVS_API_TOKEN'),
        'timeout_seconds' => (int) env('DNIPERUDEVS_TIMEOUT', 10),
        'retry_times' => (int) env('DNIPERUDEVS_RETRIES', 2),
    ],
];
