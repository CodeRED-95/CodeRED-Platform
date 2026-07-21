<?php

return [
    'enabled' => (bool) env('UBIGEO_SYNC_ENABLED', true),
    'default_source' => env('UBIGEO_SOURCE', 'alanube'),
    'minimum_rows' => 1800,
    'snapshot' => database_path('data/ubigeos_alanube.json'),
    'sources' => [
        'alanube' => [
            'url' => env('UBIGEO_ALANUBE_URL', 'https://developer.alanube.co/v1.0-PER/docs/ubigeo-table'),
            'timeout' => (int) env('UBIGEO_DOWNLOAD_TIMEOUT', 30),
            'retries' => (int) env('UBIGEO_DOWNLOAD_RETRIES', 3),
        ],
    ],
];
