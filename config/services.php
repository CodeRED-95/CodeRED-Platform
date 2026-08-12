<?php

return [
    'shalom_extractor' => [
        'enabled' => env('SHALOM_EXTRACTOR_ENABLED', true),
        'url' => env('SHALOM_EXTRACTOR_URL', 'http://shalom-extractor:3000'),
        'timeout' => (int) env('SHALOM_EXTRACTOR_TIMEOUT', 180),
        'max_file_mb' => (int) env('SHALOM_EXTRACTOR_MAX_FILE_MB', 10),
    ],

    // Declaración Jurada Shalom (packages/shalom-declaracion-jurada): app
    // Node/React independiente, servida por su propio server block de
    // Nginx (ver docker/nginx/default.conf, puerto 8091 en el host). Solo
    // se usa para el enlace de navegación del panel — CodeRED Platform no
    // hace llamadas HTTP hacia ella.
    'declaracion_jurada' => [
        'url' => env('DECLARACION_JURADA_URL'),
    ],

    'n8n' => [
        'integration_enabled' => env('N8N_INTEGRATION_ENABLED', false),
        'shared_secret' => env('N8N_SHARED_SECRET', ''),
        'webhook_url' => env('N8N_WEBHOOK_URL', ''),
        'telegram_token_requests_enabled' => env('TELEGRAM_TOKEN_REQUESTS_ENABLED', true),
        'token_request_notifications' => [
            'enabled' => env('N8N_TOKEN_REQUEST_NOTIFICATIONS', false),
            'webhook_url' => env('N8N_TOKEN_REQUEST_WEBHOOK_URL', ''),
            'secret' => env('N8N_TOKEN_REQUEST_WEBHOOK_SECRET', ''),
            'timeout' => (int) env('N8N_TOKEN_REQUEST_WEBHOOK_TIMEOUT', 10),
        ],
    ],
];
