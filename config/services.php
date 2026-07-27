<?php

return [
    'shalom_extractor' => [
        'enabled' => env('SHALOM_EXTRACTOR_ENABLED', true),
        'url' => env('SHALOM_EXTRACTOR_URL', 'http://shalom-extractor:3000'),
        'timeout' => (int) env('SHALOM_EXTRACTOR_TIMEOUT', 180),
        'max_file_mb' => (int) env('SHALOM_EXTRACTOR_MAX_FILE_MB', 10),
    ],

    'n8n' => [
        'integration_enabled' => env('N8N_INTEGRATION_ENABLED', false),
        'shared_secret' => env('N8N_SHARED_SECRET', ''),
        'webhook_url' => env('N8N_WEBHOOK_URL', ''),
        'telegram_token_requests_enabled' => env('TELEGRAM_TOKEN_REQUESTS_ENABLED', true),
    ],
];
