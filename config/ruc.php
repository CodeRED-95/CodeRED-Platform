<?php

return [
    'enabled' => (bool) env('RUC_ENABLED', true),
    'cache_enabled' => (bool) env('RUC_CACHE_ENABLED', true),
    'cache_ttl' => (int) env('RUC_CACHE_TTL', 3600),
    'rate_limit_per_minute' => (int) env('RUC_RATE_LIMIT_PER_MINUTE', 60),
    'search_rate_limit_per_minute' => (int) env('RUC_SEARCH_RATE_LIMIT_PER_MINUTE', 20),
    'import_disk' => env('RUC_IMPORT_DISK', 'local'),
    'import_directory' => env('RUC_IMPORT_DIRECTORY', 'ruc-imports'),
    'import_max_size_mb' => (int) env('RUC_IMPORT_MAX_SIZE_MB', 5000),
    'import_delimiter' => env('RUC_IMPORT_DELIMITER', '|'),
    'import_encoding' => env('RUC_IMPORT_ENCODING', 'latin-1'),
    'import_chunk_size' => (int) env('RUC_IMPORT_CHUNK_SIZE', 5000),
    'import_progress_interval' => (int) env('RUC_IMPORT_PROGRESS_INTERVAL', 1000),
    'import_queue' => env('RUC_IMPORT_QUEUE', 'ruc-imports'),
    'import_timeout' => (int) env('RUC_IMPORT_TIMEOUT', 7200),
    'import_lock_seconds' => (int) env('RUC_IMPORT_LOCK_SECONDS', 21600),
    'import_retention_days' => (int) env('RUC_IMPORT_RETENTION_DAYS', 30),
    'stalled_after_seconds' => (int) env('RUC_IMPORT_STALLED_AFTER_SECONDS', 180),
    'search_min_length' => (int) env('RUC_SEARCH_MIN_LENGTH', 3),
    'search_max_results' => (int) env('RUC_SEARCH_MAX_RESULTS', 100),
];
