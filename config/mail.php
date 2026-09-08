<?php

return [
    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [
        'resend' => [
            'transport' => 'resend',
        ],
        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => (int) env('MAIL_PORT', 2525),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],
        'array' => [
            'transport' => 'array',
        ],
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],
        'failover' => [
            'transport' => 'failover',
            'mailers' => ['resend', 'log'],
            'retry_after' => 60,
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@codered.lat'),
        'name' => env('MAIL_FROM_NAME', 'CodeRED'),
    ],

    'markdown' => [
        'theme' => 'default',
        'paths' => [resource_path('views/vendor/mail')],
    ],
];
