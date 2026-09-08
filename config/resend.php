<?php

return [
    'api_key' => env('RESEND_API_KEY'),
    'key' => env('RESEND_API_KEY'),
    'webhook_secret' => env('RESEND_WEBHOOK_SECRET'),
    'webhook' => [
        'secret' => env('RESEND_WEBHOOK_SECRET'),
    ],
];
