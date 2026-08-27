<?php

return [
    'cache_ttl' => (int) env('DNI_CACHE_TTL', 86400),
    'not_found_cache_ttl' => (int) env('DNI_NOT_FOUND_CACHE_TTL', 300),
    'persist_external_results' => (bool) env('DNI_PERSIST_EXTERNAL_RESULTS', true),
    'refresh_after_days' => (int) env('DNI_REFRESH_AFTER_DAYS', 30),
    'rate_limit_per_minute' => (int) env('DNI_API_RATE_LIMIT_PER_MINUTE', 30),
    'name_search' => [
        'enabled' => (bool) env('DNI_NAME_SEARCH_ENABLED', false),
        'cache_enabled' => (bool) env('DNI_NAME_SEARCH_CACHE_ENABLED', true),
        'cache_ttl_seconds' => (int) env('DNI_NAME_SEARCH_CACHE_TTL', 86400),
        'rate_limit_per_minute' => (int) env('DNI_NAME_SEARCH_RATE_LIMIT_PER_MINUTE', 10),
        'providers' => [
            'dniperu' => [
                'enabled' => (bool) env('DNI_NAME_SEARCH_DNIPERU_ENABLED', false),
                'url' => env('DNI_NAME_SEARCH_DNIPERU_URL', 'https://dniperu.com/buscar-dni-por-nombre/'),
                'timeout_seconds' => (int) env('DNI_NAME_SEARCH_DNIPERU_TIMEOUT', 15),
                'connect_timeout_seconds' => (int) env('DNI_NAME_SEARCH_DNIPERU_CONNECT_TIMEOUT', 5),
                'retries' => (int) env('DNI_NAME_SEARCH_DNIPERU_RETRIES', 1),
                'user_agent' => env('DNI_NAME_SEARCH_DNIPERU_USER_AGENT', 'CodeRED-Platform/4.x (public-form-client)'),
                'result_selectors' => [],
            ],
        ],
    ],

    'perudevs' => [
        'enabled' => (bool) env('DNI_PERUDEVS_ENABLED', false),
        'base_url' => env('DNI_PERUDEVS_BASE_URL', 'https://api.perudevs.com/api/v1/dni/complete'),
        'api_key' => env('DNI_PERUDEVS_API_KEY'),
        'timeout_seconds' => (int) env('DNI_PERUDEVS_TIMEOUT', 10),
        'retry_times' => (int) env('DNI_PERUDEVS_RETRIES', 2),
    ],
];
