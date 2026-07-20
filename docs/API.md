# API oficial CodeRED v1

La API de integración vive bajo `/api/v1`, usa tokens personales de Laravel Sanctum y es de solo lectura. El contrato canónico es [OpenAPI 3](openapi.yaml) y la guía operativa está en [docs/api](api/README.md).

## Endpoints

| Método | Ruta | Ability |
|---|---|---|
| GET | `/api/v1/health` | Público |
| GET | `/api/v1/agencies` | `agencies:read` |
| GET | `/api/v1/agencies/changes` | `agencies:read` |
| GET | `/api/v1/agencies/{code}` | `agencies:read` |
| GET | `/api/v1/catalog/metadata` | `agencies:read` |
| GET | `/api/v1/me` | `profile:read` |

Los endpoints heredados `search`, `version` y `snapshot` se conservan temporalmente bajo autenticación y `agencies:read` para compatibilidad de transición.

La estrategia completa de ETag, cursores, retención y recuperación está en [Sincronización del catálogo](api/synchronization.md).

## Contrato de agencia

La respuesta dedicada expone únicamente `internal_id`, `id` externo, `code`, nombre y ubicación en español, enlace de mapa, tamaño y los textos chosen terrestre/aéreo. No serializa el modelo Eloquent completo, eliminadas, auditoría ni procedencia interna.

## Seguridad

- Bearer token; nunca query string.
- 60 solicitudes/minuto/token por defecto.
- Máximo 100 registros/página por defecto.
- Expiración y revocación nativas de Sanctum.
- CORS por lista explícita; sin wildcard ni cookies.
- Errores JSON sin trazas o configuración interna.
- HTTPS obligatorio en producción.

La administración y documentación interactiva son exclusivas de Super Administrador en `/admin/api-tokens` y `/docs/api`. La guía principal genera categorías, tarjetas, parámetros y ejecución segura desde `docs/openapi.yaml`; el token Bearer vive solo en memoria y los ejemplos siempre muestran `TU_TOKEN`. Swagger UI se conserva bajo demanda como referencia OpenAPI avanzada con Authorize y Try it out. La guía usa exclusivamente las rutas relativas `/api/v1` y `/docs/api/openapi.yaml`: el navegador conserva automáticamente el origen y protocolo actuales, tanto por HTTP local como detrás de Cloudflare Tunnel.

## Autenticación y rate limiting

Sanctum representa una sesión web como `TransientToken` y un Bearer persistente como `PersonalAccessToken`. El limiter usa un bucket `user:{id}` para sesión, `token:{id}` para integraciones y `ip:{address}` cuando no existe usuario; nunca usa el secreto ni el header Authorization. El probador interactivo omite cookies únicamente al ejecutar `/api/v1`, de modo que el Bearer pegado sea la credencial realmente validada. Swagger aplica la misma regla a Try it out, pero conserva la sesión para cargar el contrato protegido. En DevTools debe observarse `Authorization: Bearer …` en la petición API, sin copiar su valor en capturas o logs.

## Ejecución desde la guía interactiva

El probador normaliza cada ruta con `buildApiPath`, que admite paths relativos o ya prefijados sin producir `/api/v1/api/v1`. Cada petición crea su propio `AbortController` con un máximo de 15 segundos. Solo una excepción lanzada por `fetch` se presenta como error de conexión; las respuestas HTTP conservan status, headers y body aunque sean 401, 403, 404, 409, 410, 422, 429 o 500. Los cuerpos 204/304, vacíos, de texto y JSON inválido se procesan sin perder el status.
