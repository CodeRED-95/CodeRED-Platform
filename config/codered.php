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

    // La politica de la extension "Registro de Actividad Shalom" se publico por
    // separado, asi que lleva su propia fecha: reutilizar la de Buscador Shalom
    // fecharia el documento antes de que existiera.
    'registro_actividad_privacy_updated_at' => env('CODERED_REGISTRO_ACTIVIDAD_PRIVACY_UPDATED_AT', '2026-08-27'),
];
