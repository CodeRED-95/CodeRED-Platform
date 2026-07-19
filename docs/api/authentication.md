# Autenticación

Sanctum almacena únicamente SHA-256 del secreto. El token completo aparece una vez y nunca debe incluirse en URLs, logs o capturas.

Abilities disponibles: `agencies:read`, `agencies:map` y `profile:read`. La API responde 401 para token ausente, inválido, revocado o expirado y 403 cuando falta una ability.

Si un token se filtra, revocarlo inmediatamente, crear otro y actualizar el cliente. HTTPS es obligatorio en producción.

## Probar desde OpenAPI

1. Genera el token en `/admin/api-tokens` y cópialo cuando se muestra por única vez.
2. Abre `/docs/api`, pulsa `Authorize` y pega el token Sanctum. Puede pegarse solo el secreto o con el prefijo `Bearer`; CodeRED normaliza el encabezado.
3. Abre una operación, usa `Try it out` y `Execute`. Swagger UI muestra solicitud, cURL, estado, cabeceras, cuerpo y duración.
4. Al terminar, recarga la página o usa `Logout` en Authorize. `persistAuthorization` está desactivado y el secreto no se guarda en Web Storage.
5. Revoca la credencial desde el panel cuando deje de ser necesaria.
