<?php

return [
    'disk' => env('AGENCY_BACKUP_DISK', 'local'),
    'directory' => 'backups/agencies',
    'maximum_backups' => (int) env('AGENCY_BACKUP_MAXIMUM', 10),
    'retention_days' => (int) env('AGENCY_BACKUP_RETENTION_DAYS', 30),
    'auto_cleanup' => (bool) env('AGENCY_BACKUP_AUTO_CLEANUP', false),
];
