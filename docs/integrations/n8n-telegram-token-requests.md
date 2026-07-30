# Solicitudes de tokens API con n8n y Telegram

CodeRED Platform permite recibir solicitudes de tokens Sanctum desde Telegram mediante n8n, dejarlas en estado pendiente y exigir aprobación manual antes de generar la credencial.

## Arquitectura

- Telegram o n8n registran una solicitud con un tipo opcional: `dni`, `ruc` o `agencies`.
- n8n valida el comando y llama a CodeRED Platform con firma HMAC.
- CodeRED Platform crea una `api_token_requests` en estado `pending`.
- Un administrador aprueba o rechaza desde `Seguridad > Solicitudes de tokens`.
- Si se aprueba, se genera un token Sanctum dentro de una transacción y el `plainTextToken` se cifra temporalmente con `Crypt::encryptString()`.
- n8n recibe un webhook de estado, consulta `retrieve`, entrega el token por Telegram y confirma la entrega.
- El token se puede recuperar una sola vez. Después se elimina `encrypted_plain_text_token`.

```mermaid
sequenceDiagram
    participant T as Telegram
    participant N as n8n
    participant C as CodeRED Platform
    participant A as Administrador

    T->>N: /token agencies
    N->>C: Crear solicitud firmada
    C-->>N: UUID y estado pending
    N-->>T: Solicitud registrada
    A->>C: Revisar solicitud
    A->>C: Elegir DNI, RUC o AGENCIAS y aprobar
    C->>C: Generar token Sanctum
    C->>N: Webhook token_request.approved
    N->>C: Recuperar token una vez
    C-->>N: Token temporal
    N-->>T: Entregar token
    N->>C: Confirmar entrega
```

## Configuración

Variables principales:

```env
N8N_INTEGRATION_ENABLED=true
N8N_SHARED_SECRET=
N8N_WEBHOOK_URL=
TELEGRAM_TOKEN_REQUESTS_ENABLED=true
```

Ajustes administrativos: `Integraciones > n8n y Telegram`.

Configurar integración activa, secreto compartido, usuarios y chats Telegram autorizados, expiraciones, abilities permitidas para auditoría de solicitudes, límites de solicitud, URL webhook, prueba de conexión y notificaciones al aprobar/rechazar. La aprobación final siempre elige DNI, RUC o AGENCIAS en Platform. El secreto completo no se muestra después de guardarlo.

## Firma HMAC

n8n debe enviar:

```text
X-CodeRED-Timestamp
X-CodeRED-Nonce
X-CodeRED-Signature
```

La firma se calcula con HMAC SHA-256:

```text
timestamp + "." + nonce + "." + cuerpo JSON sin modificar
```

CodeRED Platform rechaza timestamps con más de cinco minutos de diferencia, nonces repetidos, firmas inválidas y exceso de solicitudes. El secreto nunca viaja en el body.

## Endpoints

Todos usan `/api/v1/integrations/n8n` y middleware HMAC.

- `POST /token-requests`: crea solicitud pendiente.
- `GET /token-requests/{request_uuid}`: consulta estado no sensible.
- `POST /token-requests/{request_uuid}/retrieve`: recupera una sola vez el token aprobado.
- `POST /token-requests/{request_uuid}/delivery`: confirma entrega por Telegram.

Crear solicitud:

```json
{
  "telegram_user_id": "123456789",
  "telegram_chat_id": "123456789",
  "telegram_username": "usuario",
  "telegram_first_name": "Nombre",
  "telegram_last_name": "Apellido",
  "token_name": "Token solicitado por Telegram",
  "requested_token_type": "agencies",
  "abilities": ["agencies:read"],
  "expires_in_minutes": 60
}
```

Respuesta:

```json
{
  "success": true,
  "message": "Solicitud registrada y pendiente de aprobación.",
  "data": {
    "request_uuid": "uuid",
    "status": "pending",
    "requested_token_type": "agencies",
    "token_type": null,
    "requested_at": "fecha ISO 8601"
  }
}
```

Retrieve requiere confirmar identidad:

```json
{
  "telegram_user_id": "123456789",
  "telegram_chat_id": "123456789"
}
```

Respuesta aprobada:

```json
{
  "success": true,
  "message": "Token recuperado correctamente.",
  "data": {
    "token": "ID|TOKEN",
    "token_type": "Bearer",
    "abilities": ["agencies:read"],
    "expires_at": "fecha ISO 8601"
  }
}
```

Confirmar entrega:

```json
{
  "delivered": true,
  "telegram_message_id": "1234"
}
```

## Vigencia del token

La vigencia solicitada se envia como `requested_token_expires_in_days` entre 1 y 365. Si una integracion antigua envia `expires_in_minutes`, Platform lo acepta temporalmente y lo convierte a dias redondeando hacia arriba. La expiracion de solicitudes pendientes se controla aparte con `approval_timeout_minutes`; no define `personal_access_tokens.expires_at`. El administrador puede cambiar la vigencia final al aprobar.

## Tipos y scopes

La interfaz administrativa muestra solo tres opciones:

- **Token DNI**: `dni:consultar`.
- **Token RUC**: `ruc:consultar`, `ruc:buscar`.
- **Token AGENCIAS**: `agencias:consultar`, `agencies:read`, `agencies:map`.

`requested_token_type` es una preferencia de la solicitud. El administrador puede aprobar un tipo distinto; ese cambio queda registrado en `api_token_request_events` sin almacenar token plano ni secretos. Las solicitudes historicas sin tipo siguen abriendo y requieren seleccionar un tipo antes de aprobar.

## Estados

Solicitud: `pending`, `approved`, `rejected`, `expired`, `cancelled`.

Entrega: `not_available`, `pending`, `retrieved`, `delivered`, `failed`.

## Workflow n8n

Flujo recomendado:

```text
Telegram Trigger
→ Validar comando
→ Validar usuario
→ HTTP Request: crear solicitud
→ Telegram: solicitud registrada
→ Esperar notificación o consultar estado
→ Webhook de estado
→ IF approved/rejected
→ Si approved: recuperar token
→ Telegram: enviar token
→ HTTP Request: confirmar entrega
```

Comandos sugeridos: `/token`, `/token dni`, `/token ruc`, `/token agencies`, `/estado_token`, `/cancelar_token`. Si no se envia tipo, el administrador lo elige al aprobar.

Mensaje al registrar:

```text
Tu solicitud fue registrada.

Código: {request_uuid}
Estado: Pendiente de aprobación.

Un administrador de CodeRED Platform debe revisarla.
```

Mensaje al aprobar antes de recuperar:

```text
Tu solicitud de token fue aprobada.

El token se mostrará una sola vez y tiene una fecha de expiración.
```

Mensaje con token:

```text
Token API de CodeRED Platform

Tipo aprobado:
{token_type}

Permisos:
- agencies:read

Expira:
{expires_at}

Token:
{token}

Guárdalo en un lugar seguro. No podrá volver a mostrarse.
```

Mensaje de rechazo:

```text
Tu solicitud de token fue rechazada.

Motivo:
{rejection_reason}
```

## Seguridad y auditoría

Se registra historial en `api_token_request_events` para creación, visualización, aprobación, rechazo, generación, recuperación, entrega, revocación, vencimiento y errores de notificación. No se guarda token plano, secreto HMAC ni encabezados sensibles completos.

La aprobación usa transacción, `lockForUpdate`, validación de estado pendiente, generación Sanctum, cifrado temporal del token y job de notificación. El panel nunca muestra el token.

## Revocación

Desde el panel administrativo se puede revocar un token aprobado. Se elimina el registro de `personal_access_tokens`, se limpia cualquier token cifrado pendiente, se registra evento y se notifica a n8n.

## Mantenimiento

El comando programado marca pendientes vencidas y limpia tokens cifrados antiguos:

```bash
php artisan tokens:expire-pending-requests
```

Solución de problemas:

- `401 Firma inválida`: verificar secreto, timestamp, nonce y body JSON exacto.
- `403 Usuario/chat no autorizado`: revisar listas administrativas.
- `409 El token ya fue recuperado`: n8n no debe reintentar retrieve después de éxito.
- Webhook sin respuesta: revisar URL, secreto compartido y cola Laravel.
## Rotación de tokens

La operación "Request Token Rotation" crea una solicitud de tipo `rotation`. El token actual autentica la petición y Platform copia desde ese token el propietario, tipo funcional, scopes y fecha absoluta de caducidad. La solicitud queda pendiente y el token anterior continúa funcionando hasta que un administrador aprueba la rotación.

Al aprobar, Platform revoca el token fuente y genera un reemplazo con el mismo `expires_at`. El nuevo token se recupera una sola vez con el endpoint existente `retrieve` y se confirma con `delivery`. Las respuestas de estado incluyen `request_type`, `rotated`, `source_token_id` y `replacement_token_id`, pero nunca el token plano.

## Comandos /codigo y /rotar

El bot de Telegram debe usar un único Telegram Trigger y enrutar comandos por texto. Todos los nodos Telegram deben usar Parse Mode `None` y desactivar la atribución de n8n para evitar errores de entidades.

### /codigo

Enviar al nodo CodeRED `Personal / Get Personal Code`:

```json
{
  "telegram_user_id": "={{ String($json.message?.from?.id ?? '') }}",
  "telegram_chat_id": "={{ String($json.message?.chat?.id ?? '') }}"
}
```

Respuesta segura:

```json
{
  "success": true,
  "person_code": "a6759c4f-f6cc-4a1a-b639-3869f6894ada",
  "display_name": "Nombre del solicitante"
}
```

Si el usuario no está vinculado, Platform responde 404 con el mensaje para realizar primero una solicitud de token.

### /rotar CÓDIGO | MOTIVO

Validación recomendada:

```js
const match = text.match(
  /^\/rotar(?:@\w+)?\s+([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\s*\|\s*(.+)$/i
);
```

Enviar al nodo CodeRED `Token Requests / Request Token Rotation`:

```json
{
  "person_code": "={{ $json.person_code }}",
  "reason": "={{ $json.reason }}",
  "telegram_user_id": "={{ String($json.telegram_user_id) }}",
  "telegram_chat_id": "={{ String($json.chat_id) }}",
  "idempotency_key": "={{ `telegram-rotation-${$json.telegram_user_id}-${$json.telegram_message_id}` }}",
  "source": "telegram"
}
```

La respuesta crea una solicitud `pending`; el token actual no se revoca hasta que un administrador aprueba la rotación. Cuando `Get Token Request Status` indique `approved`, usar `Retrieve Approved Token` una sola vez, enviar el token por Telegram y luego ejecutar `Confirm Token Delivery`.
