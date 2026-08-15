# API oficial CodeRED v1

La API de integración vive bajo `/api/v1`, usa tokens personales de Laravel Sanctum y es de solo lectura. El contrato canónico es [OpenAPI 3](openapi.yaml) y la guía operativa está en [docs/api](api/README.md).

## CodeRED Mobile API

La capa móvil vive bajo `/api/v1/mobile` y reutiliza Sanctum, usuarios, roles y permisos existentes. Los tokens móviles usan ability `mobile` para identificar el cliente, pero los permisos reales siguen saliendo del RBAC del sistema.

| Método | Ruta | Protección |
|---|---|---|
| POST | `/api/v1/mobile/login` | Público, rate limit móvil |
| GET | `/api/v1/mobile/me` | `auth:sanctum` |
| POST | `/api/v1/mobile/logout` | `auth:sanctum` |

Ejemplo de login:

```json
{
  "email": "usuario@dominio.com",
  "password": "password"
}
```

Respuesta:

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Usuario",
      "email": "usuario@dominio.com"
    },
    "roles": ["admin"],
    "permissions": ["ruc.consultar", "dni.consultar"],
    "token": "TOKEN_SANCTUM"
  }
}
```

`GET /api/v1/mobile/me` devuelve el mismo bloque `user`, `roles` y `permissions` sin exponer el token. `POST /api/v1/mobile/logout` revoca únicamente el token Bearer actual.

## Endpoints

| Método | Ruta | Ability |
|---|---|---|
| GET | `/api/v1/health` | Público |
| GET | `/api/v1/agencies` | `agencies:read` |
| GET | `/api/v1/agencies/changes` | `agencies:read` |
| GET | `/api/v1/agencies/{code}` | `agencies:read` |
| GET | `/api/v1/catalog/metadata` | `agencies:read` |
| GET | `/api/v1/me` | `profile:read` |
| GET | `/api/v1/declarations` | `declaraciones:gestionar` |
| POST | `/api/v1/declarations` | `declaraciones:gestionar` |
| GET | `/api/v1/declarations/{id}` | `declaraciones:gestionar` |
| GET | `/api/v1/declarations/{id}/pdf` | `declaraciones:gestionar` |

Los endpoints heredados `search`, `version` y `snapshot` se conservan temporalmente bajo autenticación y `agencies:read` para compatibilidad de transición.

La estrategia completa de ETag, cursores, retención y recuperación está en [Sincronización del catálogo](api/synchronization.md).

## Contrato de agencia

La respuesta dedicada expone únicamente `internal_id`, `id` externo, `code`, nombre y ubicación en español, enlace de mapa, tamaño, estado operativo legible, `centro_operaciones` booleano y los textos chosen terrestre/aéreo. No serializa el modelo Eloquent completo, eliminadas, auditoría ni procedencia interna.

El schema de catálogo actual es **2**. La versión 2 agrega `estado` y `centro_operaciones` a listado, detalle, snapshot y `changes.upserted`. Los estados públicos proceden de `AgencyStatus::label()`; además de Activa, Inactiva, Cerrada temporalmente y Trasladada, el dominio real conserva En revisión porque es un estado operativo vigente.

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

El Bearer Token de la guía se mantiene en un único store Alpine en memoria. La validación y todas las tarjetas reutilizan ese estado, construyen centralmente `Accept` y `Authorization`, y ejecutan las consultas protegidas con `credentials: omit` para probar el token real sin depender de la sesión web. Limpiar autorización borra token, abilities y usuario; recargar la página recrea el store vacío.

La consola interpreta dinámicamente las abilities devueltas por `/api/v1/me`: `*` habilita acceso total y cada endpoint compara su única ability declarada antes de habilitar la ejecución. Si el token autentica pero no puede consultar su propia metadata, la UI no lo declara inválido ni inventa permisos; lo marca como no verificado y deja que el middleware de la ruta produzca el estado HTTP real. Editar el campo invalida inmediatamente la autorización anterior para evitar reutilizar abilities obsoletas.


## Declaración Jurada

La Declaración Jurada Shalom se crea y se firma en documento desde la propia API: el
PDF oficial A4 lo genera el servidor con FPDF, no el cliente. Antes ese documento sólo
existía dentro de `packages/shalom-declaracion-jurada`, construido en el navegador con
jsPDF; ese paquete pasa a ser un cliente más y puede migrar a este endpoint.

### Autorización

Dos ejes, ambos obligatorios:

- **Permiso RBAC**: `declaracion-jurada.view` habilita el módulo;
  `declaracion-jurada.manage` permite además consultar declaraciones de otras personas.
- **Ability Sanctum**: `declaraciones:gestionar`, que `MobileTokenAbilityResolver`
  concede al emitir el token únicamente si el usuario tiene `declaracion-jurada.view`.

Las rutas van con `throttle:api-declaraciones` (30/min por token) y `api.audit:declaraciones`.

### `POST /api/v1/declarations`

```json
{
  "remitente_dni": "12345678",
  "remitente_nombre": "MARIA FERNANDEZ",
  "remitente_telefono": "987654321",
  "destinatario_dni": "87654321",
  "destinatario_nombre": "JUAN PEREZ",
  "destinatario_telefono": "912345678",
  "agency_id": 521,
  "motivo_envio": "Traslado de enseres",
  "items": [{ "cantidad": "2", "descripcion": "Cajas de ropa" }]
}
```

`agency_id` es la clave interna de la agencia y debe existir en el catálogo. El nombre de
la sede lo fija el servidor desde ese catálogo y queda **congelado** en la declaración: si
la agencia se renombra o se traslada después, el documento histórico no cambia. Los
documentos aceptan 8 dígitos (DNI) o 9 (carné de extranjería), y hace falta al menos un bien.

Responde `201` con `{ "success": true, "message": "...", "data": { ... } }`. Errores:
`401` sin sesión, `403` sin permiso o sin ability, `422` con `errors` por campo, `429` por límite.

### `GET /api/v1/declarations`

Historial paginado. Un usuario normal ve sólo las suyas; con `declaracion-jurada.manage`
ve todas. No devuelve el PDF, sólo `pdf_available`.

### `GET /api/v1/declarations/{id}/pdf`

Devuelve el documento con `Content-Type: application/pdf` y un nombre seguro
(`declaracion-jurada-{documento}-{AAAAMMDD}.pdf`). El archivo vive en el disco privado y
nunca se sirve por una URL pública ni predecible. Si el archivo se hubiera perdido, se
regenera al vuelo: la fila de base de datos es la fuente de verdad.

Una declaración sólo la descarga su autor, salvo que quien la pida tenga
`declaracion-jurada.manage`.
