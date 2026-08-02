<?php

return [
    'enabled' => env('CODERED_EVENTS_ENABLED', true),
    'timeout' => (int) env('CODERED_EVENTS_TIMEOUT', 15),
    'retry' => (int) env('CODERED_EVENTS_RETRY', 5),
    'queue' => env('CODERED_EVENTS_QUEUE', true),
    'agent' => [
        'url' => rtrim((string) env('CODERED_AGENT_LOCAL_URL', env('CODERED_AGENT_PUBLIC_URL', 'http://codered-agent:5680')), '/'),
        'token' => env('CODERED_AGENT_LOCAL_API_TOKEN', ''),
        'events_path' => '/v1/events',
    ],
];
