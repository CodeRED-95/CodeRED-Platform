<?php

declare(strict_types=1);

return [
    /*
     * Vida del access token, en minutos. Corto a propósito: es la ventana máxima
     * durante la que una sesión revocada podría seguir respondiendo si algo
     * fallara en la comprobación en vivo.
     */
    'access_token_ttl' => (int) env('CLIENT_SESSION_ACCESS_TTL_MINUTES', 15),

    /*
     * Vida del refresh token, en días. Se renueva en cada rotación, así que un
     * cliente en uso habitual no vuelve a pedir credenciales.
     */
    'refresh_token_ttl' => (int) env('CLIENT_SESSION_REFRESH_TTL_DAYS', 30),

    /*
     * Sesiones activas simultáneas por usuario y aplicación. Al superarse se
     * revoca la más antigua: evita que un dispositivo perdido acumule sesiones
     * indefinidamente sin molestar a quien usa móvil y escritorio a la vez.
     */
    'max_sessions_per_application' => (int) env('CLIENT_SESSION_MAX_PER_APP', 5),

    /*
     * Revocar las sesiones de cliente al cambiar la contraseña. Los tokens de
     * API de integración no se tocan: no representan a la persona y su ciclo de
     * vida lo gobierna la administración de tokens.
     */
    'revoke_on_password_change' => (bool) env('CLIENT_SESSION_REVOKE_ON_PASSWORD_CHANGE', true),
];
