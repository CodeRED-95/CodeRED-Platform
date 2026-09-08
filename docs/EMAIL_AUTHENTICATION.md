# Correo transaccional y verificación de cuentas

CodeRED Platform usa la cuenta CodeRED como identidad común para Platform,
Mobile y Desktop. Las cuentas creadas por el registro público tienen
`email_verification_required = true` y deben confirmar un código numérico de
seis dígitos antes de acceder al panel o recibir un token Sanctum.

## Diseño

- `email_verification_codes` guarda únicamente el hash del código, su
  expiración, intentos y timestamps. El código dura 10 minutos, es de un solo
  uso y admite cinco intentos.
- `EmailVerificationService` es la fuente única para registro, login,
  reenvío, cambio de correo y API. Las llamadas se protegen por usuario/correo
  e IP, con 60 segundos de cooldown.
- `email_logs` registra metadatos de entrega sin OTP, contraseñas ni secretos.
  El envío se ejecuta en Redis mediante `SendTransactionalEmail` con tres
  intentos y backoff progresivo.
- Resend es el proveedor transaccional oficial. La clave sólo vive en
  `RESEND_API_KEY` del backend. El remitente es
  `CodeRED <no-reply@mail.codered.lat>`.
- `POST /api/v1/webhooks/resend` verifica el cuerpo crudo y las cabeceras
  `svix-id`, `svix-timestamp` y `svix-signature` con el SDK oficial de Resend.
  Los eventos se deduplican por `svix-id`.

## Compatibilidad histórica

La migración crea `email_verification_required` con valor `false`. Así los
usuarios existentes que nunca usaron verificación no pierden acceso. Sólo el
registro nuevo y el cambio de correo activan el requisito explícitamente; al
verificar, `email_verified_at` queda registrado.

## API para clientes

- `POST /api/v1/auth/email/send-code`
- `POST /api/v1/auth/email/verify-code`
- `POST /api/v1/auth/email/resend-code`

Las respuestas nunca incluyen el código, su hash, la API key ni detalles del
proveedor. El login de una cuenta pendiente devuelve HTTP 409 con
`verification_required: true`, sin emitir tokens.

## Operación

Configurar en el `.env` productivo de Platform, sin versionarlo:

```dotenv
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=no-reply@mail.codered.lat
MAIL_FROM_NAME="CodeRED"
RESEND_API_KEY=
RESEND_WEBHOOK_SECRET=
```

Después de desplegar, registrar en Resend el webhook
`https://platform.codered.lat/api/v1/webhooks/resend` para los eventos
`email.sent`, `email.delivered`, `email.delivery_delayed`, `email.bounced`,
`email.complained` y `email.failed`.
