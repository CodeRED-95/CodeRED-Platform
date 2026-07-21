<?php

return [
    'enabled' => (bool) env('RENIEC_ENABLED', true),
    'import' => [
        'disk' => env('RENIEC_IMPORT_DISK', 'local'),
        'incoming_directory' => env('RENIEC_IMPORT_INCOMING_DIRECTORY', 'private/reniec/incoming'),
        'working_directory' => env('RENIEC_IMPORT_WORKING_DIRECTORY', 'private/reniec/working'),
        'archive_directory' => env('RENIEC_IMPORT_ARCHIVE_DIRECTORY', 'private/reniec/archive'),
        'errors_directory' => env('RENIEC_IMPORT_ERRORS_DIRECTORY', 'private/reniec/errors'),
        'queue' => env('RENIEC_IMPORT_QUEUE', 'reniec-imports'),
        'encoding' => env('RENIEC_IMPORT_ENCODING', 'ISO-8859-1'),
        'delimiter' => env('RENIEC_IMPORT_DELIMITER', '|'),
        'chunk_size' => (int) env('RENIEC_IMPORT_CHUNK_SIZE', 10000),
        'progress_interval' => (int) env('RENIEC_IMPORT_PROGRESS_INTERVAL', 10000),
        'checkpoint_interval' => (int) env('RENIEC_IMPORT_CHECKPOINT_INTERVAL', 50000),
        'timeout' => (int) env('RENIEC_IMPORT_TIMEOUT', 86400),
        'lock_seconds' => (int) env('RENIEC_IMPORT_LOCK_SECONDS', 172800),
        'max_size_mb' => (int) env('RENIEC_IMPORT_MAX_SIZE_MB', 30000),
        'resume_enabled' => (bool) env('RENIEC_IMPORT_RESUME_ENABLED', true),
        'archive_files' => (bool) env('RENIEC_IMPORT_ARCHIVE_FILES', true),
        'retention_days' => (int) env('RENIEC_IMPORT_RETENTION_DAYS', 180),
        'error_retention_days' => (int) env('RENIEC_IMPORT_ERROR_RETENTION_DAYS', 365),
        'strategy' => env('RENIEC_IMPORT_STRATEGY', 'insert_ignore'),
        'copy_batch_size' => (int) env('RENIEC_IMPORT_COPY_BATCH_SIZE', 100000),
        'staging_unlogged' => (bool) env('RENIEC_IMPORT_STAGING_UNLOGGED', true),
        'validate_checksum' => (bool) env('RENIEC_IMPORT_VALIDATE_CHECKSUM', true),
    ],
];
