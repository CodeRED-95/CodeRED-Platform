<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Token Request Configuration
    |--------------------------------------------------------------------------
    */

    // OTP Configuration
    'otp' => [
        'expires_in_minutes' => env('TOKEN_REQUEST_OTP_EXPIRES_IN_MINUTES', 10),
        'max_attempts' => env('TOKEN_REQUEST_OTP_MAX_ATTEMPTS', 5),
        'max_resends' => env('TOKEN_REQUEST_OTP_MAX_RESENDS', 3),
    ],

    // Token Reveal Configuration
    'token_reveal' => [
        // Máximo número de veces que se puede revelar un token (0 = ilimitado)
        'max_times' => env('TOKEN_REQUEST_MAX_REVEAL_TIMES', 1),
    ],

    // Protected Data View Configuration
    'protected_data' => [
        // Requiere permiso específico para ver datos
        'requires_permission' => env('TOKEN_REQUEST_PROTECTED_DATA_REQUIRES_PERMISSION', true),
        'permission_name' => 'api-token-requests.view-protected-data',
    ],

    // Auditoría
    'audit' => [
        // Registrar todas las acciones de auditoría
        'enabled' => env('TOKEN_REQUEST_AUDIT_ENABLED', true),
        // Incluir User Agent en logs
        'include_user_agent' => env('TOKEN_REQUEST_AUDIT_INCLUDE_USER_AGENT', true),
        // Incluir IP en logs
        'include_ip' => env('TOKEN_REQUEST_AUDIT_INCLUDE_IP', true),
    ],

    // Delivery Configuration
    'delivery' => [
        // Métodos de entrega disponibles
        'methods' => [
            'presencial' => 'Presencial',
            'llamada' => 'Llamada telefónica',
            'canal_corporativo' => 'Canal corporativo',
            'otro' => 'Otro',
        ],
    ],
];
