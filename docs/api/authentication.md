# Autenticación

Sanctum almacena únicamente SHA-256 del secreto. El token completo aparece una vez y nunca debe incluirse en URLs, logs o capturas.

Abilities disponibles: `agencies:read`, `agencies:map` y `profile:read`. La API responde 401 para token ausente, inválido, revocado o expirado y 403 cuando falta una ability.

Si un token se filtra, revocarlo inmediatamente, crear otro y actualizar el cliente. HTTPS es obligatorio en producción.
