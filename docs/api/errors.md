# Errores y límites

| HTTP | Mensaje |
|---|---|
| 401 | No autenticado / token expirado |
| 403 | Ability insuficiente |
| 404 | Agencia no encontrada |
| 422 | Parámetros inválidos |
| 429 | Límite de solicitudes superado |
| 500 | Error inesperado sin traza |

El límite predeterminado es 60 solicitudes por minuto y por token. CORS acepta únicamente los orígenes definidos en `API_ALLOWED_ORIGINS`, incluidos orígenes explícitos `chrome-extension://ID`. La extensión también deberá declarar `host_permissions` para el dominio API.
