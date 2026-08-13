# Declaración Jurada Shalom

Aplicación React con generación de PDF, venta manual de consultas y autocompletado de nombres por DNI.

## Integración con CodeRED Platform

Este paquete vive dentro de `CodeRED-Platform/packages/` y se ejecuta como el servicio
`declaracion-jurada` del `docker-compose.yml` raíz del monorepo. Es una aplicación
separada (despliegue, sesiones y datos de negocio propios en SQLite), pero **ya no
tiene sistema de usuarios propio**: CodeRED Platform es la única fuente de identidad.

- **Autenticación**: el login (`POST /api/auth/login`) valida el email y la contraseña
  directamente contra la tabla `users` de CodeRED Platform (mismo hash bcrypt que usa
  Laravel — nunca se copia ni se re-hashea), exige que la cuenta esté activa
  (`status = 'active'`, sin `deleted_at`) y que tenga el permiso
  `declaracion-jurada.view`. No existe registro, recuperación de contraseña ni
  administración de usuarios propia: todo eso se hace en CodeRED Platform. La
  identidad canónica de cada usuario es `users.id` de CodeRED (`codered_user_id` en
  este paquete) — el email es solo informativo y puede cambiar sin romper el
  historial de créditos/consultas.
- **Permisos**: `declaracion-jurada.view` habilita el login; `declaracion-jurada.manage`
  habilita el panel administrador de esta app (precios, métodos de pago, proveedor
  DNI, Telegram, aprobar compras). Ambos son permisos normales de CodeRED Platform
  (`database/seeders/PermissionsSeeder.php` en la raíz del monorepo) — no hay un RBAC
  paralelo aquí.
- **Conexión a la base de datos**: `CODERED_DB_HOST` (y el resto de `CODERED_DB_*`) son
  **obligatorios** — la app falla al arrancar sin ellos. Usa un rol PostgreSQL de solo
  lectura dedicado (`declaracion_jurada_ro`, creado por la migración
  `2026_08_13_000001_create_declaracion_jurada_readonly_role` en la raíz del monorepo)
  con `SELECT` únicamente sobre `users`, `roles`, `permissions`, `role_user` y
  `permission_role` — no puede escribir ni acceder a ninguna otra tabla.
- **Consultas DNI**: si `CODERED_API_TOKEN` está configurado, `GET /api/dni/{dni}`
  resuelve el nombre vía `GET {CODERED_API_URL}/api/v1/dni/{dni}` (token Sanctum con
  ability `dni:consultar`) en vez de llamar directamente al proveedor externo. El
  token se emite una sola vez con `docker compose exec app php artisan
  declaracion-jurada:setup` desde la raíz de CodeRED Platform. Sin esa variable, se
  usa el flujo original (`DNI_API_URL` + clave de proveedor configurada en el panel
  admin de esta app).

Ver `docker-compose.yml` (servicio `declaracion-jurada`) y `.env.example` de la raíz
del monorepo para el resto del cableado (Nginx, volumen de datos, etc.).

**Esta app ya no puede desplegarse de forma verdaderamente independiente**: necesita
una CodeRED Platform accesible (Postgres para autenticar, opcionalmente su API para
resolver DNIs). El servidor Node y el SQLite propio siguen siendo suyos — solo la
identidad de usuario se delega.

## Configuración

1. Copia `.env.example` como `.env.local`.
2. Completa `CODERED_DB_HOST`/`CODERED_DB_PORT`/`CODERED_DB_DATABASE`/`CODERED_DB_USERNAME`/`CODERED_DB_PASSWORD`
   apuntando a la base PostgreSQL de una instancia de CodeRED Platform.
3. Cambia `APP_ENCRYPTION_KEY` (cifra la clave del proveedor DNI y el token de Telegram
   guardados en el panel — no contraseñas de usuario).
4. Configura Telegram desde el panel administrador (requiere `declaracion-jurada.manage`
   en CodeRED). También puedes usar `TELEGRAM_BOT_TOKEN` y `TELEGRAM_CHAT_ID` en `.env.local`.
5. Ejecuta `npm install` y `npm run dev`.
6. Ingresa con una cuenta de CodeRED Platform que tenga el permiso `declaracion-jurada.view`.

La base SQLite se crea automáticamente en `.data/declaracion-jurada.db` y solo guarda
datos propios de esta app (créditos, paquetes, métodos de pago, sesiones locales,
caché de permisos) — nunca contraseñas ni datos de identidad más allá de una copia
informativa del email/nombre.

## Flujo de créditos

1. El usuario inicia sesión con su cuenta de CodeRED Platform (debe tener el permiso
   `declaracion-jurada.view`).
2. El administrador (permiso `declaracion-jurada.manage`) configura la API de consulta
   DNI, los paquetes y los métodos de pago.
3. El usuario elige un paquete, un método de pago e ingresa su referencia.
4. El usuario adjunta una captura PNG, JPG o WebP de hasta 5 MB. La solicitud y la imagen se envían al chat de Telegram y la imagen no se guarda en SQLite.
5. El administrador comprueba el pago y aprueba o rechaza la solicitud.
6. Al aprobar, se crea un lote independiente con su propia cantidad y fecha de vencimiento; una compra nueva no modifica los lotes anteriores.
7. Cada consulta DNI exitosa descuenta un crédito del lote vigente que vence primero; si el servicio falla, el crédito se devuelve al mismo lote.

Las consultas automáticas de remitente y destinatario se controlan por separado desde sus interruptores. Apagadas no llaman a la API ni consumen créditos.

Los campos de documento admiten DNI de 8 dígitos y carnet de extranjería de hasta 9 dígitos. El autocompletado consume un punto únicamente para DNI de 8 dígitos; el nombre asociado a un C.E. se ingresa manualmente.

Los métodos de pago admiten una imagen PNG, JPG o WebP de hasta 5 MB, útil para códigos QR o instrucciones visuales. Al eliminar una compra aprobada se elimina únicamente el saldo restante de su lote; las consultas ya realizadas permanecen en el contador de uso.

La clave del proveedor DNI se cifra con AES-256-GCM usando `APP_ENCRYPTION_KEY` y nunca se envía al navegador.

## Producción

Se despliega como el servicio `declaracion-jurada` del `docker-compose.yml` raíz de
CodeRED Platform (ver ese archivo para el wiring completo). Después de
`npm run build`, el servidor de producción se inicia con `npm start` y escucha el
puerto indicado por `PORT`.

### Variables necesarias

```env
CODERED_DB_HOST=postgres
CODERED_DB_PORT=5432
CODERED_DB_DATABASE=codered
CODERED_DB_USERNAME=declaracion_jurada_ro
CODERED_DB_PASSWORD=...
APP_ENCRYPTION_KEY=un-secreto-largo-y-aleatorio
DATABASE_PATH=/data/declaracion-jurada.db
COOKIE_SECURE=true
DNI_API_URL=https://api.perudevs.com/api/v1/dni/simple
```

Puedes generar `APP_ENCRYPTION_KEY` con:

```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

No cambies esa clave después de guardar la clave del proveedor DNI o el token de
Telegram: se usa para cifrarlas.

### Servicios externos

- **CodeRED Platform:** obligatorio — provee identidad de usuario y, opcionalmente, la API de consulta DNI.
- **Consulta DNI (opcional, si no se usa el bridge de CodeRED):** una clave activa del proveedor, configurada desde el panel administrador.
- **Telegram:** un bot creado con BotFather y el Chat ID donde recibirá comprobantes.

### Operación y seguridad

- Mantén una sola instancia de la aplicación mientras utilices SQLite.
- Conserva la base en un volumen persistente y realiza respaldos periódicos.
- No subas `.env.local`, `.data` ni claves al repositorio.
- Mantén `COOKIE_SECURE=true` en producción y usa únicamente HTTPS.
- El rol PostgreSQL usado (`CODERED_DB_USERNAME`) debe ser de solo lectura y limitado
  a `users`/`roles`/`permissions`/`role_user`/`permission_role` — nunca las
  credenciales completas de la app CodeRED.

## Pruebas

```bash
npm test
```

Ejecuta `test/auth.test.js` (Node's `node --test`, sin dependencias adicionales) con
un doble en memoria de PostgreSQL: cubre login válido/inválido, permisos
`declaracion-jurada.view`/`.manage`, cuentas desactivadas/eliminadas en CodeRED,
cambio de contraseña/email, logout, rutas privadas sin sesión, intentos de inyección
SQL y rate limiting.
