<?php

return [
    'enabled' => (bool) env('RUC_ENABLED', true),
    'cache_enabled' => (bool) env('RUC_CACHE_ENABLED', true),
    'cache_ttl' => (int) env('RUC_CACHE_TTL', 3600),
    'rate_limit_per_minute' => (int) env('RUC_RATE_LIMIT_PER_MINUTE', 60),
    'search_rate_limit_per_minute' => (int) env('RUC_SEARCH_RATE_LIMIT_PER_MINUTE', 20),
    'search_min_length' => (int) env('RUC_SEARCH_MIN_LENGTH', 3),
    'search_max_results' => (int) env('RUC_SEARCH_MAX_RESULTS', 100),
    'backup' => [
        // Debe ser <= los límites reales de la infraestructura (nunca al
        // revés): docker/php/php.ini tiene upload_max_filesize=5G y
        // post_max_size=5100M; docker/nginx/default.conf tiene
        // client_max_body_size 5G. Si subes este valor, sube también esos
        // tres al mismo tiempo — ver docs-ruc/BACKUP_SYSTEM.md "Límites".
        'max_upload_mb' => (int) env('RUC_BACKUP_MAX_UPLOAD_MB', 5000),

        // Backups multipart (manifest.json + *.partNNNN generados por
        // packages/ruc-tools). Cada parte llega en un request HTTP
        // independiente — el límite real que importa aquí es el de
        // Cloudflare (100 MB en planes free/pro), no el de nginx/PHP (que
        // ya son generosos, ver arriba). max_part_size_mb es un techo duro
        // del servidor, independiente de lo que declare el manifest.
        'multipart' => [
            'max_part_size_mb' => (int) env('RUC_BACKUP_MULTIPART_MAX_PART_MB', 95),
            'max_total_parts' => (int) env('RUC_BACKUP_MULTIPART_MAX_PARTS', 500),
            'max_total_size_mb' => (int) env('RUC_BACKUP_MULTIPART_MAX_TOTAL_MB', 20000),
            'session_expires_hours' => (int) env('RUC_BACKUP_MULTIPART_EXPIRES_HOURS', 24),
            'supported_format_versions' => [1],
        ],

        // RestoreRucBackupJob corre en la cola/conexión dedicada
        // "ruc-backups" (ver config/queue.php): un restore de 18M+ filas
        // puede tardar varias horas. queue debe coincidir con
        // config('queue.connections.ruc-backups.queue').
        'restore' => [
            'queue' => env('RUC_BACKUP_QUEUE', 'ruc-backups'),
            'timeout' => (int) env('RUC_BACKUP_RESTORE_TIMEOUT', 86400),
        ],
    ],
];
