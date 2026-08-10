<?php

use App\Support\Version;

return [
    'name' => env('APP_NAME', 'CodeRED Platform'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'America/Lima'),
    'locale' => env('APP_LOCALE', 'es'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'es'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'es_PE'),
    // Fuente única de verdad: composer.json > extra.version (ver config/version.php).
    'version' => Version::current(),
];
