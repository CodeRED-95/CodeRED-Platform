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