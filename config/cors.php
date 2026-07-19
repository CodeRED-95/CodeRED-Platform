<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'OPTIONS'],
    'allowed_origins' => config('api.allowed_origins', []),
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Origin'],
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After'],
    'max_age' => 3600,
    'supports_credentials' => false,
];
