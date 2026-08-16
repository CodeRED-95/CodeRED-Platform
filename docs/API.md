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
| GET | `/api/v1/notifications` | `mobile` |
| GET | `/api/v1/notifications/unread-count` | `mobile` |
| POST | `/api/v1/notifications/{id}/read` | `mobile` |
| POST | `/api/v1/notifications/read-all` | `mobile` |
| GET | `/api/v1/activity` | `mobile` |
| GET · POST | `/api/v1/admin/tokens` | `admin:tokens` |
| GET | `/api/v1/admin/tokens/types` | `admin:tokens` |
| DELETE | `/api/v1/admin/tokens/{id}` | `admin:tokens` |
| GET | `/api/v1/admin/token-requests` | `admin:solicitudes` |
| GET | `/api/v1/admin/token-requests/{id}` | `admin:solicitudes` |
| POST | `/api/v1/admin/token-requests/{id}/approve` | `admin:solicitudes` |
| POST | `/api/v1/admin/token-requests/{id}/reject` | `admin:solicitudes` |
| GET | `/api/v1/admin/users` | `admin:usuarios` |
| GET | `/api/v1/admin/users/{id}` | `admin:usuarios` |

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

#### Dos clientes, un solo emisor del PDF

Consumen esta misma API la app **CodeRED Mobile** y el paquete **Shalom Declaración
Jurada** (`packages/shalom-declaracion-jurada`). Ninguno compone el documento: el PDF A4
lo genera siempre el servidor, de modo que ambos descargan un archivo idéntico.

Se diferencian sólo en cómo autentican:

| Cliente | Credencial | Usuario efectivo |
| --- | --- | --- |
| CodeRED Mobile | token personal del usuario | el dueño del token |
| Shalom Declaración Jurada | token técnico del `ApiClient` | el de `X-CodeRED-User-Id` |

El paquete React no maneja tokens en el navegador: su servidor Node añade el token
técnico y la cabecera `X-CodeRED-User-Id` con el usuario que tiene la sesión abierta
allí. Estas rutas llevan `api.delegate-user`, así que `ResolveDelegatedUser` valida ese
usuario —debe existir, estar activo y tener el `delegation_permission` del cliente— y
`DeclarationController` lo toma como actor: la declaración queda a nombre de la persona,
no del servicio, y el historial que ve es el suyo.

Un token técnico **sin** esa cabecera recibe `401`: un servicio no tiene declaraciones
propias. Un cliente sin `can_delegate_users` que envíe la cabecera recibe `403`.


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

## Notificaciones

Centro de notificaciones de CodeRED Mobile. Se apoya en el canal `database` de
**Laravel Notifications** —tabla `notifications`, `read_at`, `markAsRead()`—, no en un
almacén propio. El bus de eventos de plataforma (`App\Services\Events`, que entrega al
agente y a n8n) es otra cosa: sin destinatario ni estado de lectura, y sigue igual.

- **Ability Sanctum**: `mobile`, que lleva todo token emitido por el login móvil. No hace
  falta un permiso RBAC nuevo porque una notificación no es un módulo: es correspondencia
  personal. Los tokens técnicos (el bridge React, n8n) no tienen esa ability y reciben
  `403`; si por algún camino la tuvieran, el controlador responde `401` porque un servicio
  no tiene notificaciones propias.
- **Aislamiento**: todas las consultas parten de `$user->notifications()`. Una notificación
  ajena responde `404`, no `403`: confirmar que existe ya sería filtrar información.
- Las rutas van con `throttle:api-mobile`.

### `GET /api/v1/notifications`

Historial paginado, más recientes primero. El bloque `meta` incluye `no_leidas`, para que
la pantalla pinte el contador sin una segunda petición.

```json
{
  "success": true,
  "data": [
    {
      "id": "9b1f…",
      "tipo": "declaracion.generada",
      "titulo": "Declaración generada",
      "mensaje": "DJ-2026-000005 para 01 DE MAYO ya está disponible.",
      "destino": "declaraciones",
      "referencia_id": 5,
      "leida": false,
      "creada_en": "2026-08-16T09:12:44-05:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 3, "total": 42, "no_leidas": 7 }
}
```

`destino` es lo que la app usa para navegar al tocar la notificación; hoy sólo existe
`declaraciones` (abre «Mis declaraciones») y `ninguno`. El cliente no conoce cada `tipo`:
pinta `titulo` y `mensaje`, así que una notificación nueva que reutilice un destino
existente no obliga a publicar una versión de la app.

El contenido **nunca lleva documentos ni nombres de personas**: una notificación puede
leerse en la pantalla de bloqueo.

### `GET /api/v1/notifications/unread-count`

Sólo el contador (`{ "data": { "no_leidas": 7 } }`). Lo consulta el Dashboard, que no
necesita la lista.

### `POST /api/v1/notifications/{id}/read` · `POST /api/v1/notifications/read-all`

Marcan una o todas como leídas y devuelven el contador actualizado. `read-all` sólo
alcanza las del usuario autenticado.

### Notificaciones existentes

| Tipo | Cuándo | Destinatario |
| --- | --- | --- |
| `declaracion.generada` | `POST /api/v1/declarations` emite el documento | el autor de la declaración |

Se envía en cola (Redis, `ShouldQueue`): el PDF ya está emitido y el cliente no espera por
el aviso. Si la cola estuviera caída, la declaración sigue siendo válida y visible en el
historial.

Las solicitudes de token de Telegram (`token.request.*`) **no** generan notificación móvil:
quien las solicita no es un usuario de CodeRED —`api_token_requests` identifica al
solicitante por Telegram y correo, sin `user_id`—, así que no hay a quién notificar en la
app.

---

## Administración móvil

Permite a CodeRED Mobile administrar tokens, solicitudes de token y usuarios. **No es
un sistema nuevo**: los tokens siguen siendo Sanctum sobre `personal_access_tokens`, y
aprobar o rechazar una solicitud usa las mismas acciones que el panel web
(`App\Actions\ApiTokenRequests\{Approve,Reject}TokenRequestAction`), extraídas de
`App\Livewire\Admin\ApiTokenRequests\Index` para que ambos frontales compartan una
sola implementación.

### Los dos ejes

| | |
| --- | --- |
| **Ability** | abre el área: `admin:tokens`, `admin:solicitudes`, `admin:usuarios` |
| **Permiso RBAC** | habilita la acción concreta, y se comprueba en cada petición |

`MobileTokenAbilityResolver` concede cada ability sólo si el usuario tiene el permiso de
**lectura** del área (`api-tokens.view-any`, `api-token-requests.view`, `users.view`).
Dentro, el controlador exige el permiso de la acción: `api-tokens.create-for-users` para
emitir, `api-tokens.revoke-any` para revocar, `api-token-requests.approve` y `.reject`
para decidir.

Tener la ability **no basta**: un token emitido ayer la conserva aunque a la persona le
hayan retirado el permiso después, así que el permiso se consulta contra la base cada
vez. Un token técnico (`ApiClient`) que llegara con la ability recibe `401`: la
administración es de personas, con nombre y responsabilidad.

Las rutas van con `throttle:api-admin` (30/min) y `api.audit:admin`.

### Tokens

`GET /api/v1/admin/tokens` lista con `search` y `estado=activo`. **Nunca devuelve el
valor de un token**: la columna guarda un hash SHA-256 del que no se puede volver al
original, así que no existe nada que mostrar.

`GET /api/v1/admin/tokens/types` devuelve los tipos de `ApiTokenType` con las abilities
que concede cada uno y los límites de vigencia. **Las abilities no son texto libre**: las
decide el tipo, igual que en el panel, así que ningún cliente puede pedir una combinación
arbitraria.

`POST /api/v1/admin/tokens` recibe `nombre`, `tipo`, `vigencia_dias` y `usuario_id`.
Responde `201` con el valor plano **una sola vez**, junto al aviso de que no se podrá
volver a ver. Ese valor no se registra en ningún log.

`DELETE /api/v1/admin/tokens/{id}` marca `revoked_at` sin borrar la fila: la auditoría de
peticiones pasadas debe seguir sabiendo a qué token apuntaban. Revocar dos veces da `422`.

### Solicitudes de token

`GET /api/v1/admin/token-requests` filtra por `estado` (los valores reales de
`ApiTokenRequestStatus`) y busca por `search` sobre el código de seguimiento y la
aplicación. El nombre del solicitante está cifrado en columna y no se puede filtrar por
SQL, así que no se ofrece esa búsqueda.

El detalle expone lo necesario para decidir —solicitante, propósito, tipo pedido,
abilities pedidas, canal— y **el contacto de entrega siempre enmascarado**: verlo completo
exige `api-token-requests.view-delivery-contact` y ocurre en el panel web, que registra
esa revelación como un evento aparte. Tampoco se exponen `token_ciphertext`, `token_hash`
ni `token_last_four`.

`approve` recibe `nombre_token`, `tipo_token`, `vigencia_dias` y `usuario_id`. La emisión,
el cifrado en la bóveda, los eventos de auditoría y el aviso a n8n ocurren **en el
servidor**. El token aprobado **no vuelve en la respuesta**: se entrega por el canal
acordado. `reject` acepta un `motivo` opcional que queda registrado como evento propio.

Una solicitud ya resuelta responde `422` con el motivo.

### Usuarios

`GET /api/v1/admin/users` pagina y busca por nombre o correo, con sus roles. El recurso no
serializa `password`, `remember_token` ni identificadores de Telegram.

Esta versión es de **sólo lectura** a propósito: crear, editar o cambiar el estado de una
persona son acciones con salvaguardas (un administrador no puede desactivarse a sí mismo
ni quedarse sin rol) que hoy viven en el panel web. Exponerlas por API exigiría replicar
esas protecciones, no sólo el endpoint.

---

## Actividad reciente

`GET /api/v1/activity` devuelve los últimos movimientos del usuario autenticado, para
la sección homónima del dashboard móvil. Ability `mobile`, `throttle:api-mobile`.

**No crea ningún registro nuevo**: lee `api_request_logs`, la auditoría que ya escribe
`AuditApiRequest` en cada llamada. La atribución sale del token —los tokens personales
pertenecen a un usuario—, así que cada quien ve únicamente lo suyo.

```json
{ "success": true,
  "data": [ { "servicio": "dni", "titulo": "Consulta DNI", "ocurrido_en": "2026-08-16T00:50:00-05:00" } ] }
```

Tres reglas que la definen:

- **Sólo llamadas correctas** (2xx). Un 403 o un 500 son ruido para quien mira «qué hice
  hoy», y ya se auditan aparte para diagnóstico.
- **Filtrada por los permisos de hoy**: si a alguien le retiran `dni-records.view`, sus
  consultas de DNI desaparecen de la lista. El resumen no puede delatar módulos a los
  que ya no tiene acceso.
- **Nunca el identificador consultado**: viaja el servicio y el momento, no el endpoint
  ni el DNI o RUC en claro.

`limit` por defecto 5, acotado a 20.

---
