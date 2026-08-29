<?php

return [
    'enabled' => (bool) env('ANIME_ENABLED', true),
    'default_language' => env('ANIME_DEFAULT_LANGUAGE', 'es'),
    'request_timeout' => (int) env('ANIME_REQUEST_TIMEOUT', 15),
    'connect_timeout' => (int) env('ANIME_CONNECT_TIMEOUT', 10),
    'cache' => [
        'enabled' => (bool) env('ANIME_CACHE_ENABLED', true),
        'store' => env('ANIME_CACHE_STORE', env('CACHE_STORE', 'redis')),
        'mirror_database' => (bool) env('ANIME_CACHE_MIRROR_DATABASE', true),
        'search_ttl' => (int) env('ANIME_CACHE_SEARCH_TTL', 3600),
        'metadata_ttl' => (int) env('ANIME_CACHE_METADATA_TTL', 86400),
        'episodes_ttl' => (int) env('ANIME_CACHE_EPISODES_TTL', 3600),
        'servers_ttl' => (int) env('ANIME_CACHE_SERVERS_TTL', 300),
    ],
    'server_priority' => array_values(array_filter(array_map('trim', explode(',', (string) env('ANIME_SERVER_PRIORITY', 'desu,magi'))))),
    'rate_limits' => [
        'search' => (int) env('ANIME_RATE_LIMIT_SEARCH', 30),
        'metadata' => (int) env('ANIME_RATE_LIMIT_METADATA', 60),
        'episodes' => (int) env('ANIME_RATE_LIMIT_EPISODES', 60),
        'stream' => (int) env('ANIME_RATE_LIMIT_STREAM', 20),
    ],
    'providers' => [
        'jkanime' => [
            'enabled' => (bool) env('JKANIME_ENABLED', true),
            'base_url' => env('JKANIME_BASE_URL', 'https://jkanime.net'),
            'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('JKANIME_ALLOWED_HOSTS', 'jkanime.net,www.jkanime.net'))))),
            'stream_allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('JKANIME_STREAM_ALLOWED_HOSTS', 'jkanime.net,www.jkanime.net,playmudos.com,nika.playmudos.com'))))),
            'user_agent' => env('JKANIME_USER_AGENT', 'CodeRED-Platform/4.x Anime Provider'),
        ],
        'anilist' => [
            'enabled' => (bool) env('ANILIST_ENABLED', true),
            'base_url' => env('ANILIST_BASE_URL', 'https://graphql.anilist.co'),
            'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('ANILIST_ALLOWED_HOSTS', 'graphql.anilist.co'))))),
            'user_agent' => env('ANILIST_USER_AGENT', 'CodeRED-Platform/4.x Anime Metadata Provider'),
            'search_limit' => (int) env('ANILIST_SEARCH_LIMIT', 10),
        ],
    ],
];
