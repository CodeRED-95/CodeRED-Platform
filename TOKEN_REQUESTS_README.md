# Sistema de Solicitudes de Token - Documentación Completa

## 📋 Descripción General

El sistema de solicitudes de token de CodeRED Platform proporciona un flujo seguro y auditado para que usuarios externos soliciten acceso mediante tokens API. El sistema incluye:

- **Solicitudes públicas** sin autenticación
- **Validación con OTP** (one-time password)
- **Revelación única de token** con transacciones garantizadas
- **Auditoría completa** de todas las acciones
- **Cifrado de datos personales** con AES-256-CBC
- **Permisos granulares** para administradores

---

## 🔄 FLUJO DE SOLICITUD PÚBLICA

### 1. Creación de Solicitud

El usuario accede a `/solicitar-token` y completa el formulario con:
- Nombre del solicitante
- Email de contacto
- Número de teléfono
- Motivo de la solicitud
- Método de entrega (WhatsApp, Email, etc.)
- Aceptación de términos

```
POST /api/token-requests
{
  "requester_name": "Juan Pérez",
  "requester_email": "juan@example.com",
  "requester_phone": "+51987654321",
  "purpose": "Integración con Buscador Shalom",
  "delivery_method": "whatsapp"
}
```

**Seguridad en esta fase:**
- ✅ Datos personales cifrados con AES-256-CBC inmediatamente
- ✅ Email indexado con HMAC-SHA256 (blind index)
- ✅ Tracking code generado: `CR-` + 10 caracteres aleatorios
- ✅ Rate limiting: máximo X solicitudes por IP cada Y minutos
- ✅ Auditoría: evento "Request Created"

---

### 2. Consulta de Estado

El usuario consulta el estado de su solicitud en `/solicitar-token`:

```
GET /token-requests/status
{
  "tracking_code": "CR-XXXXXXXXXX",
  "email": "juan@example.com"
}
```

**Respuesta:**
```json
{
  "status": "pending|approved|rejected",
  "delivery_status": "pending|delivered",
  "message": "Tu solicitud está siendo revisada"
}
```

**Seguridad:**
- ✅ No se expone información personal
- ✅ Solo se acepta email cifrado con blind index
- ✅ Auditoría: evento "Status Checked"

---

### 3. Solicitud de OTP

Cuando la solicitud está **aprobada**, el usuario solicita un OTP:

```
POST /token-requests/otp/request
{
  "tracking_code": "CR-XXXXXXXXXX"
}
```

**Qué sucede:**
1. Sistema genera código OTP de 6 dígitos
2. Código se hashea con bcrypt (nunca plaintext)
3. Se envía por email al usuario
4. Expira en 10 minutos
5. Máximo 5 intentos de verificación
6. Máximo 3 reenvíos
7. Auditoría: evento "OTP Requested"

---

### 4. Verificación de OTP

El usuario verifica el código recibido:

```
POST /token-requests/otp/verify
{
  "tracking_code": "CR-XXXXXXXXXX",
  "code": "123456"
}
```

**Validaciones:**
- ✅ Código tiene exactamente 6 dígitos
- ✅ Código no está expirado (10 minutos)
- ✅ No se superaron 5 intentos
- ✅ Hash del código coincide con almacenado

**Auditoría:** evento "OTP Verified"

---

### 5. Revelación de Token

Solo después de verificar OTP, el usuario puede revelar el token:

```
POST /token-requests/token/reveal
{
  "tracking_code": "CR-XXXXXXXXXX"
}
```

**Qué sucede:**
1. Transacción de BD con `lockForUpdate()` para evitar race conditions
2. Verifica que el token NO ha sido revelado antes
3. Descifra el token (solo en memoria)
4. Marca `token_revealed_at` = ahora
5. Incrementa contador de revelaciones
6. Retorna el token AL USUARIO
7. Token NO se guarda en BD, solo se muestra
8. Auditoría: evento "Token Revealed"

**Importante:** El token se muestra **UNA SOLA VEZ**. No puede volver a mostrarse.

---

### 6. Confirmación de Entrega

El usuario confirma que recibió el token:

```
POST /token-requests/delivery/confirm
{
  "tracking_code": "CR-XXXXXXXXXX",
  "method": "whatsapp"
}
```

**Auditoría:** evento "Delivery Confirmed"

---

## 👨‍💼 FLUJO ADMINISTRATIVO

### 1. Ver Solicitudes

Los administradores pueden ver todas las solicitudes en el panel:
- Filtrar por estado (pendiente, aprobada, rechazada)
- Filtrar por estado de entrega
- Filtrar por fecha
- Búsqueda por solicitante

### 2. Aprobar Solicitud

```
POST /api/token-requests/{id}/approve
{
  "token_type": "agencies",
  "expires_in_days": 30
}
```

Se genera un nuevo token Sanctum con los permisos solicitados.

### 3. Ver Datos Protegidos

Solo administradores con permiso `api-token-requests.view-protected-data`:

```
GET /api/token-requests/{id}/protected-data
```

**Qué se descifra:**
- Nombre del solicitante
- Teléfono
- Motivo
- Método de entrega

**Importante:** Los datos se descifran SOLO EN MEMORIA, nunca se guardan plaintext.

**Auditoría:** evento "Protected Data Viewed"

### 4. Revelar Token

Solo administradores con permiso `api-token-requests.reveal_token`:

```
POST /api/token-requests/{id}/token/reveal
```

Se revela el token dentro de una transacción con `lockForUpdate()`.

**Auditoría:** evento "Token Revealed"

### 5. Confirmar Entrega

```
POST /api/token-requests/{id}/delivery/confirm
{
  "method": "presencial",
  "reason": "Entregado en persona a Juan Pérez"
}
```

Marca la solicitud como "Entregado" y registra quién, cuándo y cómo.

**Auditoría:** evento "Delivery Confirmed"

---

## 🔐 SEGURIDAD

### Cifrado de Datos

Los siguientes campos se cifran con **AES-256-CBC autenticado**:
- `requester_name` → `requester_name_encrypted`
- `requester_phone` → `requester_phone_encrypted`
- `purpose` → `purpose_encrypted`
- `delivery_method` → `delivery_method_encrypted`
- `token_ciphertext` (el token mismo)

**Punto importante:** Los datos se descifran ÚNICAMENTE cuando se accede al atributo del modelo. El acceso desencadena el desciframiento automático.

```php
$request = ApiTokenRequest::find(1);
$name = $request->requester_name; // ← Aquí se descifra
// $name nunca se guarda en BD, solo en memoria
```

### Blind Indexing

El email se indexa con **HMAC-SHA256** para búsquedas sin descifrar:

```php
$blindIndex = hash_hmac('sha256', strtolower(trim($email)), $key);
```

Esto permite:
- ✅ Consultar por email sin descifrar
- ✅ Prevenir búsquedas de fuerza bruta
- ✅ No almacenar email en plaintext

### OTP Security

- ✅ Códigos hasheados con bcrypt
- ✅ Expiración en 10 minutos
- ✅ Máximo 5 intentos
- ✅ Máximo 3 reenvíos
- ✅ Se invalidan después de usar

### Token Revelation

- ✅ Una única revelación por solicitud
- ✅ Transacción con `lockForUpdate()` previene race conditions
- ✅ El token NO se guarda en plaintext en BD
- ✅ Solo se desencripta en memoria cuando se revela
- ✅ Se retorna al usuario pero NO se persiste

### Auditoría

Todas las acciones se registran en `api_token_request_audit_logs`:

```sql
SELECT action, user_id, ip_address, created_at 
FROM api_token_request_audit_logs 
WHERE api_token_request_id = :id
ORDER BY created_at DESC;
```

Acciones auditadas:
- `otp_requested` - Usuario solicita código OTP
- `otp_verified` - Usuario verifica código OTP
- `otp_expired` - Código OTP expiró
- `otp_max_attempts_reached` - Usuario agotó intentos
- `protected_data_viewed` - Admin ve datos sensibles
- `token_revealed` - Token fue revelado
- `delivery_confirmed` - Entrega confirmada
- `approval_cancelled` - Admin cancela aprobación

Cada auditoría registra:
- Usuario (si es admin) o null (si es público)
- IP address
- User Agent
- Timestamp
- Detalles específicos de la acción

---

## 📊 ESTADOS Y TRANSICIONES

### Estados de Solicitud

```
PENDING → APPROVED → (Token Revealed) → DELIVERED
        ↓
        REJECTED
        ↓
        CANCELLED
```

### Estados de Entrega

```
PENDING → DELIVERED
   ↓
   FAILED (opcional, si entrega falla)
```

---

## 🔑 PERMISOS REQUERIDOS

Para que los administradores puedan realizar acciones:

| Acción | Permiso | Descripción |
|--------|---------|-------------|
| Ver solicitudes | `api-token-requests.view` | Listar todas las solicitudes |
| Ver detalles | `api-token-requests.view` | Acceder a detalles de solicitud |
| Aprobar | `api-token-requests.approve` | Generar token y aprobar |
| Rechazar | `api-token-requests.reject` | Rechazar solicitud |
| Ver datos | `api-token-requests.view-protected-data` | Descifrar datos personales |
| Revelar token | `api-token-requests.reveal_token` | Mostrar token aprobado |
| Confirmar entrega | `api-token-requests.confirm-delivery` | Marcar como entregado |
| Cancelar | `api-token-requests.cancel-approval` | Cancelar aprobación |

---

## 🧪 TESTING

### Ejecutar Pruebas

```bash
# Todas las pruebas de OTP y tokens
php artisan test tests/Feature/OtpAndTokenRevealTest.php

# Pruebas de solicitud pública completa
php artisan test tests/Feature/PublicTokenRequestWebTest.php

# Pruebas de administrador
php artisan test tests/Feature/Admin/ApiTokenRequests/
```

### Casos de Prueba

- ✅ Generación de OTP válido
- ✅ Verificación de OTP expirado
- ✅ Verificación de máximo de intentos
- ✅ Revelación única de token
- ✅ Prevención de doble revelación
- ✅ Confirmación de entrega
- ✅ Auditoría completa
- ✅ Permisos administrativos

---

## ⚙️ CONFIGURACIÓN

### .env

```env
# OTP Configuration
TOKEN_REQUEST_OTP_EXPIRES_IN_MINUTES=10
TOKEN_REQUEST_OTP_MAX_ATTEMPTS=5
TOKEN_REQUEST_OTP_MAX_RESENDS=3

# Encryption
TOKEN_REQUEST_DATA_ENCRYPTION_KEY=base64:xxxxx
TOKEN_REQUEST_BLIND_INDEX_KEY=xxxxx

# Token Reveal
TOKEN_REQUEST_MAX_REVEAL_TIMES=1
```

### config/token-requests.php

Configuración centralizada para:
- Expiración de OTP
- Máximo de intentos
- Máximo de reenvíos
- Métodos de entrega permitidos
- Permisos requeridos

---

## 📧 EMAIL

### Plantilla OTP

Ubicación: `resources/views/emails/otp-code.blade.php`

Incluye:
- Código de 6 dígitos
- Tiempo de expiración
- Límites de intentos
- Link a formulario de verificación
- Información de seguridad

---

## 🚨 TROUBLESHOOTING

### "Código OTP inválido"
- Verificar que el código tenga 6 dígitos
- Verificar que no esté expirado (10 minutos)
- Verificar que no se superaron 5 intentos

### "El token ya fue revelado"
- El token solo puede verse UNA VEZ
- No se puede volver a mostrarse
- Si el usuario lo perdió, debe contactar a administrador

### "No se encontró la solicitud"
- Verificar que el tracking code es correcto
- Verificar que el email es el correcto
- Los datos son sensibles (no revela si existe o no)

---

## 📝 VERSIÓN

- **Versión:** 2.3.0
- **Fecha:** 2026-08-06
- **Estado:** Producción
- **Soporte:** Contactar a DevOps

---

## 🔗 ENLACES RELACIONADOS

- [API Documentation](/docs/api/token-requests)
- [Security Policy](/docs/security)
- [Changelog](/CHANGELOG.md)
- [Architecture](/docs/architecture)

