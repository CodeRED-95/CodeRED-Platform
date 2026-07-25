<?php

return [
    'shalom_extractor' => [
        'enabled' => env('SHALOM_EXTRACTOR_ENABLED', true),
        'url' => env('SHALOM_EXTRACTOR_URL', 'http://shalom-extractor:3000'),
        'timeout' => (int) env('SHALOM_EXTRACTOR_TIMEOUT', 180),
        'max_file_mb' => (int) env('SHALOM_EXTRACTOR_MAX_FILE_MB', 10),
    ],
];
