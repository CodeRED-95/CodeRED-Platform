<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Notificaciones push
    |--------------------------------------------------------------------------
    |
    | El interruptor existe para poder desactivar la entrega inmediata sin
    | tocar código ni perder el historial: con esto en false las notificaciones
    | se siguen guardando y leyendo en el centro de notificaciones, sólo deja de
    | sonar el teléfono.
    |
    | Las credenciales las lee kreait/laravel-firebase de FIREBASE_CREDENTIALS,
    | que apunta a un archivo montado desde fuera del repositorio. Ver
    | docs/FIREBASE_SETUP.md.
    |
    */

    'fcm' => [
        'enabled' => (bool) env('FIREBASE_PUSH_ENABLED', true),
    ],

];
