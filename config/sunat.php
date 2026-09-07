<?php

return [
    'representatives' => [
        'url' => env('SUNAT_REPRESENTATIVES_URL', 'https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/FrameCriterioBusquedaWeb.jsp'),
        'timeout' => (int) env('SUNAT_REPRESENTATIVES_TIMEOUT', 60),
        'connect_timeout' => (int) env('SUNAT_REPRESENTATIVES_CONNECT_TIMEOUT', 15),
        'cache_ttl' => (int) env('SUNAT_REPRESENTATIVES_CACHE_TTL', 86400),
        'rate_limit' => (int) env('SUNAT_REPRESENTATIVES_RATE_LIMIT', 20),
        'user_agent' => env('SUNAT_REPRESENTATIVES_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36'),
        'cache_enabled' => (bool) env('SUNAT_REPRESENTATIVES_CACHE_ENABLED', true),
    ],
];
