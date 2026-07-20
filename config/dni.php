<?php

return [
    'provider' => env('DNI_PROVIDER', 'current'),
    'api_url' => env('DNI_API_URL', ''),
    'api_token' => env('DNI_API_TOKEN', ''),
    'cache_ttl' => (int) env('DNI_CACHE_TTL', 86400),
    'not_found_cache_ttl' => (int) env('DNI_NOT_FOUND_CACHE_TTL', 300),
    'rate_limit_per_minute' => (int) env('DNI_RATE_LIMIT_PER_MINUTE', 30),
    'timeout_seconds' => (int) env('DNI_API_TIMEOUT_SECONDS', 20),
    'connect_timeout_seconds' => (int) env('DNI_API_CONNECT_TIMEOUT_SECONDS', 10),
];
