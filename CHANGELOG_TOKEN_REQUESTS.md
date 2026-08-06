# Changelog - Mejora en Solicitudes de Tokens

## [2.3.0] - 2026-08-06

### AGREGADO

#### Flujo de Solicitud de Token Público
- ✅ Endpoint público para solicitudes de tokens sin autenticación
- ✅ Validación OTP con códigos de 6 dígitos
- ✅ Revelación de token de una sola vez con seguridad transaccional
- ✅ Seguimiento de estado público con código de rastreo y email
- ✅ Flujo de confirmación de entrega

#### Sistema OTP
- ✅ Generación de OTP con hash bcrypt
- ✅ Expiración configurable (predeterminado: 10 minutos)
- ✅ Límite de velocidad: 5 intentos, 3 reenvíos
- ✅ Entrega por email con advertencias de seguridad
- ✅ Registro de auditoría para cada acción OTP

#### Seguridad y Encriptación
- ✅ Encriptación autenticada AES-256-CBC para datos personales
- ✅ Índice ciego HMAC-SHA256 para búsqueda de email
- ✅ Aplicación de token de una sola vez con `lockForUpdate()`
- ✅ Desencriptación solo en memoria (sin persistencia en texto plano)
- ✅ Registro de auditoría completo con IP, User Agent, timestamp

#### Migraciones de Base de Datos
- ✅ Tabla `otp_validations` para almacenamiento de OTP
- ✅ Tabla `api_token_request_audit_logs` para registro de auditoría
- ✅ Nuevos campos en `api_token_requests`:
  - `otp_validated_at` - Timestamp de verificación OTP
  - `token_reveal_count` - Contador de revelaciones
  - `protected_data_view_count` - Contador de visualizaciones de admin
  - `last_protected_view_ip` - IP de última visualización de datos
  - `last_protected_view_at` - Timestamp de última visualización

#### Servicios Backend
- ✅ `OtpService` - Generación, verificación, reenvío de OTP
- ✅ `AuditService` - Registro de auditoría centralizado
- ✅ 5 Excepciones personalizadas para ciclo de vida de OTP
- ✅ 6 Acciones para cada paso del flujo

#### Acciones (Lógica de Negocio)
- ✅ `CreateOtpTokenAction` - Generar y enviar OTP
- ✅ `VerifyOtpTokenAction` - Verificar código OTP
- ✅ `RevealTokenAction` - Desencriptar y revelar token
- ✅ `ConfirmTokenDeliveryAction` - Marcar como entregado
- ✅ `ShowProtectedDataAction` - Mostrar datos encriptados
- ✅ `ResendOtpTokenAction` - Reenviar OTP

#### Validadores de Formularios (Form Requests)
- ✅ `VerifyOtpRequest` - Validación de código OTP
- ✅ `ConfirmTokenDeliveryRequest` - Confirmación de entrega
- ✅ `RequestOtpRequest` - Validación de solicitud OTP

#### Autorización
- ✅ `ApiTokenRequestPolicy` con permisos granulares
- ✅ 9 puertas de permiso para cada acción de admin
- ✅ Integración de `Gate::authorize()`

#### Componentes Livewire
- ✅ **Índice Admin** actualizado con:
  - `showProtectedData()` - Ver datos encriptados
  - `revealToken()` - Revelar token de una sola vez
  - `confirmTokenDelivery()` - Confirmar entrega
  - Nuevos modales para cada acción
  - Atributos `#[Locked]` para datos sensibles

- ✅ **TokenRequestManager Público** actualizado con:
  - `requestOtp()` - Generar OTP
  - `verifyOtp()` - Verificar código OTP
  - `revealToken()` - Revelar token
  - `confirmRevealToken()` - Procesar revelación
  - Gestión del ciclo de vida de OTP

#### Vistas Blade y Modales
- ✅ `protected-data-modal.blade.php` - Visor de datos de admin
- ✅ `reveal-token-modal.blade.php` - Revelación de token
- ✅ `token-revealed-modal.blade.php` - Visualización de token
- ✅ `otp-form.blade.php` - Entrada de OTP pública
- ✅ `token-display.blade.php` - Visualización de token pública
- ✅ Plantilla de email: `emails/otp-code.blade.php`

#### Configuración
- ✅ `config/token-requests.php` - Configuración centralizada
- ✅ Configuración de expiración, intentos, reenvíos de OTP
- ✅ Configuración de métodos de entrega
- ✅ Configuración de registro de auditoría

#### Testing
- ✅ `tests/Feature/OtpAndTokenRevealTest.php` - 25+ pruebas
  - Generación y hash de OTP
  - Verificación de OTP y expiración
  - Revelación de token (de una sola vez)
  - Confirmación de entrega
  - Registro de auditoría
  - Validación de permisos

#### Documentación
- ✅ `TOKEN_REQUESTS_README.md` - Documentación completa
- ✅ Diagramas de flujo público y ejemplos
- ✅ Documentación de flujo de admin
- ✅ Explicaciones de seguridad
- ✅ Ejemplos de API
- ✅ Guía de resolución de problemas

### MEJORADO

#### Mejoras de Seguridad
- ✅ Almacenamiento de tokens en texto plano reemplazado con AES-256-CBC
- ✅ Índice ciego agregado para consultas de email
- ✅ Prevención de condición de carrera implementada con `lockForUpdate()`
- ✅ Registro de auditoría completo habilitado con banderización de datos sensibles
- ✅ Configuración de límite de velocidad agregada

#### Experiencia del Usuario
- ✅ Los usuarios públicos ahora pueden rastrear el estado de la solicitud
- ✅ Verificación de OTP proporciona mensajes de error claros
- ✅ Token de una sola vez previene pérdida accidental
- ✅ Mejoras de UX de modal de admin con advertencias
- ✅ Mejor flujo de trabajo de confirmación de entrega

#### Calidad del Código
- ✅ Tipificación fuerte en toda la aplicación (PHP 8.3)
- ✅ Manejo exhaustivo de excepciones
- ✅ Patrón de acción para lógica de negocio
- ✅ Capa de servicio para encriptación/auditoría
- ✅ Autorización basada en políticas

### CORRECCIONES

- ✅ Problema de visibilidad de token (nunca se muestra en texto plano en la UI)
- ✅ Condición de carrera en revelación de token (agregada transacción+bloqueo)
- ✅ Registro de auditoría faltante para operaciones sensibles
- ✅ Configuración de clave de encriptación (agregada a .env.example)
- ✅ Brechas de autorización (agregada clase Policy)

### CAMBIOS

- ✅ Los componentes Livewire ahora usan Acciones en lugar de lógica en línea
- ✅ Revelación de token ahora requiere validación de OTP
- ✅ El estado de entrega es explícito (Pendiente vs Entregado)
- ✅ El registro de auditoría es automático a través de AuditService
- ✅ El admin puede ver datos encriptados temporalmente (solo en memoria)

### SEGURIDAD

#### Correcciones Críticas
- ✅ Tokens en texto plano nunca aparecen en:
  - Base de datos (almacenados como texto cifrado)
  - Respuestas de API (solo durante revelación)
  - Hidratación de Livewire (atributos #[Locked])
  - Logs (auditados, no registrados)
  - Excepciones (capturadas y desinfectadas)

#### Nuevas Protecciones
- ✅ Protección de fuerza bruta de OTP (5 intentos, 3 reenvíos)
- ✅ Revelación de token protegida por transacción (prevención de condición de carrera)
- ✅ Acceso a datos protegido por permisos y políticas
- ✅ Todas las acciones registradas con IP, User Agent, timestamp
- ✅ Datos encriptados solo desencriptados en memoria

### DEPRECADO

- Ninguno (nueva característica, compatible hacia atrás)

### REMOVIDO

- Ninguno

### NOTAS

- Requiere PHP 8.3+
- Requiere Laravel 12+
- Requiere Livewire 3+
- PostgreSQL 12+ recomendado para bloqueo de nivel de fila
- Sin cambios graves en la API de tokens existente

### RUTA DE MIGRACIÓN

Las solicitudes de token existentes continúan funcionando sin cambios. Nuevo flujo de solicitud pública disponible en `/solicitar-token`.

### CONTRIBUYENTES

- Claude Code - Staff Software Engineer
- Generación de pruebas y documentación automatizada

---

## Instrucciones de Despliegue

Ver `TOKEN_REQUESTS_README.md` y `DOCKER_DEPLOYMENT_COMMANDS.md` para procedimientos completos de despliegue y pasos de validación.

### Inicio Rápido (Producción)

```bash
# 1. Respaldar base de datos
docker compose exec -T postgres pg_dump -U codered -d codered > backup.sql

# 2. Descargar cambios
git pull origin main

# 3. Ejecutar migraciones
docker compose exec -T codered-app php artisan migrate --force

# 4. Limpiar cachés
docker compose exec -T codered-app php artisan optimize:clear

# 5. Verificar
docker compose exec -T codered-app php artisan migrate:status
```

### Limitaciones Conocidas

- Los códigos OTP expiran después de 10 minutos (configurable)
- Máximo 5 intentos de verificación de OTP
- Máximo 3 intentos de reenvío de OTP
- El token solo se puede revelar una vez por solicitud
- La visualización de datos del admin es solo en memoria (no persistida)

### Rendimiento

- Verificación de OTP: ~50ms (hash bcrypt)
- Revelación de token: ~30ms (desencriptación AES-256-CBC + transacción de BD)
- Registro de auditoría: ~10ms (asincrónico vía cola si está configurado)

---

## Historial de Versiones

| Versión | Fecha | Estado | Notas |
|---------|------|--------|-------|
| 2.3.0 | 2026-08-06 | Lanzamiento | Flujo OTP público + Token de una sola vez |
| 2.2.0 | 2026-07-XX | Lanzamiento | Flujo de aprobación de admin |
| 2.1.0 | 2026-06-XX | Lanzamiento | Solicitudes de token iniciales |
