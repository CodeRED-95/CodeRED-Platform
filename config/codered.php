<?php

return [
    'dev_admin' => [
        'name' => env('DEV_ADMIN_NAME', ''),
        'email' => env('DEV_ADMIN_EMAIL', ''),
        'password' => env('DEV_ADMIN_PASSWORD', ''),
    ],

    'legal_name' => env('CODERED_LEGAL_NAME', 'CodeRED Platform'),
    'legal_country' => env('CODERED_LEGAL_COUNTRY', 'Perú'),
    'support_email' => env('CODERED_SUPPORT_EMAIL', 'support@codered.lat'),
    'privacy_updated_at' => env('CODERED_PRIVACY_UPDATED_AT', '2026-08-02'),
];
