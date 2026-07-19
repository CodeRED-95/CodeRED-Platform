<?php

return [
    'enabled' => (bool) env('API_ENABLED', true),
    'version' => env('API_VERSION', 'v1'),
    'docs_enabled' => (bool) env('API_DOCS_ENABLED', true),
    'docs_require_auth' => (bool) env('API_DOCS_REQUIRE_AUTH', true),
    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 60),
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('API_ALLOWED_ORIGINS', 'http://localhost:8090'))))),
    'default_token_expiration_days' => (int) env('API_DEFAULT_TOKEN_EXPIRATION_DAYS', 90),
    'max_per_page' => (int) env('API_MAX_PER_PAGE', 100),
    'abilities' => [
        'agencies:read' => 'Consultar agencias',
        'agencies:map' => 'Consultar datos cartográficos',
        'profile:read' => 'Consultar propietario del token',
    ],
];
