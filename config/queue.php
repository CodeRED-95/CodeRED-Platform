<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 7500),
            'block_for' => null,
            'after_commit' => false,
        ],
        // Conexión DEDICADA para RestoreRucBackupJob (nunca comparte
        // retry_after con "redis"): retry_after es una
        // propiedad de la CONEXIÓN, no de la cola, y debe ser mayor que el
        // timeout más largo de cualquier job despachado por ella. Un
        // restore de ruc_records puede tardar hasta 24h (ver
        // RestoreRucBackupJob::$timeout); si retry_after fuera menor
        // (p. ej. los 7500s que usan las otras conexiones), Laravel daría
        // por muerto el job y lo re-entregaría a otro worker MIENTRAS el
        // primero sigue con TRUNCATE+COPY en curso — dos procesos psql
        // tocando ruc_records a la vez. Ver docs-ruc/BACKUP_SYSTEM.md.
        'ruc-backups' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('RUC_BACKUP_QUEUE', 'ruc-backups'),
            'retry_after' => (int) env('RUC_BACKUP_QUEUE_RETRY_AFTER', 90000),
            'block_for' => null,
            'after_commit' => false,
        ],
    ],
];
