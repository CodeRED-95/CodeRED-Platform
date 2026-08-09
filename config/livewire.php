<?php

return [
    'class_namespace' => 'App\\Livewire',
    'view_path' => resource_path('views/livewire'),
    'layout' => 'layouts.app',
    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['file', 'max:'.(max(1, (int) env('RUC_BACKUP_MAX_UPLOAD_MB', 5000)) * 1024)],
        'directory' => 'livewire-tmp',
        'middleware' => 'throttle:60,1',
    ],
];
