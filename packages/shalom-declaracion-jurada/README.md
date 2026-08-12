# Declaración Jurada Shalom

Aplicación React con generación de PDF, autenticación, venta manual de consultas y autocompletado de nombres por DNI.

## Integración con CodeRED Platform

Este paquete vive dentro de `CodeRED-Platform/packages/` y, cuando se ejecuta como el
servicio `declaracion-jurada` del `docker-compose.yml` raíz del monorepo, queda
interconectado con la plataforma sin dejar de ser una aplicación separada
(base de datos, sesiones y despliegue propios):

- **Consultas DNI**: si `CODERED_API_TOKEN` está configurado, `GET /api/dni/{dni}`
  resuelve el nombre vía `GET {CODERED_API_URL}/api/v1/dni/{dni}` (token Sanctum con
  ability `dni:consultar`) en vez de llamar directamente al proveedor externo. El
  token se emite una sola vez con `docker compose exec app php artisan
  declaracion-jurada:setup` desde la raíz de CodeRED Platform. Sin esa variable, se
  usa el flujo original (`DNI_API_URL` + clave de proveedor configurada en el panel
  admin de esta app).
- **Usuarios**: si `CODERED_DB_HOST` (y el resto de `CODERED_DB_*`) están configurados,
  cada registro/login intenta enlazar (por email, de forma best-effort y solo lectura)
  la cuenta con un usuario existente de la tabla `users` de CodeRED Platform. El
  resultado se expone como `coderedLinked`/`coderedName` en la sesión del usuario.

Ver `docker-compose.yml` (servicio `declaracion-jurada`) y `.env.example` de la raíz
del monorepo para el resto del cableado (Nginx, volumen de datos, etc.).

## Configuración

1. Copia `.env.example` como `.env.local`.
2. Cambia `ADMIN_EMAIL`, `ADMIN_PASSWORD` y `APP_ENCRYPTION_KEY`.
3. Configura `RESEND_API_KEY` y `EMAIL_FROM` para enviar los códigos de verificación.
4. Configura Telegram desde el panel administrador. También puedes usar `TELEGRAM_BOT_TOKEN` y `TELEGRAM_CHAT_ID` en `.env.local`.
5. Ejecuta `npm install` y `npm run dev`.
6. Ingresa con la cuenta administradora configurada.

La base SQLite se crea automáticamente en `.data/declaracion-jurada.db`.

## Flujo de créditos

1. El usuario verifica su correo con un código y completa el registro.
2. El administrador configura la API global, los paquetes y los métodos de pago.
3. El usuario elige un paquete, un método de pago e ingresa su referencia.
4. El usuario adjunta una captura PNG, JPG o WebP de hasta 5 MB. La solicitud y la imagen se envían al chat de Telegram y la imagen no se guarda en SQLite.
5. El administrador comprueba el pago y aprueba o rechaza la solicitud.
6. Al aprobar, se crea un lote independiente con su propia cantidad y fecha de vencimiento; una compra nueva no modifica los lotes anteriores.
7. Cada consulta DNI exitosa descuenta un crédito del lote vigente que vence primero; si el servicio falla, el crédito se devuelve al mismo lote.

Las consultas automáticas de remitente y destinatario se controlan por separado desde sus interruptores. Apagadas no llaman a la API ni consumen créditos.

Los campos de documento admiten DNI de 8 dígitos y carnet de extranjería de hasta 9 dígitos. El autocompletado consume un punto únicamente para DNI de 8 dígitos; el nombre asociado a un C.E. se ingresa manualmente.

## Verificación por correo

El registro puede utilizar Mailgun o Resend para entregar el código de seis dígitos. Para Mailgun:

1. Agrega tu dominio en [Mailgun](https://app.mailgun.com/mg/sending/domains) y copia en Cloudflare los registros DNS que Mailgun indique.
2. Espera que el dominio aparezca como verificado y crea una API Key de envío.
3. En el panel administrador abre **Correo**, selecciona **Mailgun** e ingresa la API Key, el dominio exacto y la región de tu cuenta.
4. Configura un remitente del dominio verificado, por ejemplo `Declaración Jurada <registro@tudominio.com>`.

Para utilizar Resend:

1. Crea una cuenta en [Resend](https://resend.com/) y genera una API Key con permiso de envío.
2. Agrega y verifica tu dominio en Resend siguiendo los registros DNS que te indique.
3. En el panel administrador abre **Correo** e ingresa la API Key.
4. Configura un remitente del dominio verificado, por ejemplo `Declaración Jurada <registro@tudominio.com>`.

La API Key se cifra antes de guardarse. `EMAIL_PROVIDER`, `MAILGUN_API_KEY`, `MAILGUN_DOMAIN`, `MAILGUN_REGION`, `RESEND_API_KEY` y `EMAIL_FROM` permanecen disponibles como configuración alternativa mediante variables de entorno.

El mismo servicio de correo permite recuperar una contraseña desde **Olvidé mi contraseña**. El código de recuperación dura diez minutos, admite hasta cinco intentos y no puede reutilizarse como código de registro.

## Acceso con Google

1. Abre [Google Cloud Console](https://console.cloud.google.com/apis/credentials) y crea o selecciona un proyecto.
2. Configura la pantalla de consentimiento de OAuth.
3. Crea un cliente OAuth 2.0 de tipo **Aplicación web**.
4. Agrega `http://localhost:5173` y la dirección HTTPS de producción en **Orígenes de JavaScript autorizados**.
5. Copia el Client ID y guárdalo en **Panel administrador → Correo → Acceso con Google**.

Esta integración utiliza Google Identity Services y valida el ID token en el servidor. No necesita guardar un Client Secret. También puede configurarse con `GOOGLE_CLIENT_ID` en el entorno.

Los métodos de pago admiten una imagen PNG, JPG o WebP de hasta 5 MB, útil para códigos QR o instrucciones visuales. Al eliminar una compra aprobada se elimina únicamente el saldo restante de su lote; las consultas ya realizadas permanecen en el contador de uso.

La clave de la API se cifra con AES-256-GCM usando `APP_ENCRYPTION_KEY` y nunca se envía al navegador.

## Producción

La aplicación incluye un servidor Node de producción. Después de ejecutar `npm run build`, se inicia con `npm start` y escucha el puerto indicado por `PORT`.

### Opción recomendada: Railway

1. Sube el proyecto a un repositorio privado de GitHub.
2. En [Railway](https://railway.com/) crea un proyecto desde ese repositorio.
3. Usa `npm ci && npm run build` como comando de construcción y `npm start` como comando de inicio.
4. Agrega un volumen persistente y móntalo en `/data`.
5. Configura `DATABASE_PATH=/data/declaracion-jurada.db`.
6. Agrega las variables de entorno indicadas abajo y despliega el servicio.
7. Genera un dominio de Railway o conecta tu dominio propio. Railway termina HTTPS por ti.

También puedes usar un servicio Node con disco persistente como [Render](https://render.com/docs/disks), [Fly.io](https://fly.io/docs/volumes/) o un VPS. Vercel, Netlify y otros entornos serverless no son adecuados para esta versión mientras use SQLite local, porque el sistema de archivos puede ser efímero.

### Variables necesarias

```env
ADMIN_EMAIL=tu-correo@dominio.com
ADMIN_PASSWORD=una-clave-administradora-segura
APP_ENCRYPTION_KEY=un-secreto-largo-y-aleatorio
DATABASE_PATH=/data/declaracion-jurada.db
COOKIE_SECURE=true
DNI_API_URL=https://api.perudevs.com/api/v1/dni/simple
```

Puedes generar `APP_ENCRYPTION_KEY` con:

```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

No cambies esa clave después de guardar las credenciales de API, Telegram o correo: se utiliza para cifrarlas. La cuenta administradora se crea en la primera ejecución de una base nueva.

### Servicios externos

- **Consulta DNI:** una clave activa del proveedor, configurada desde el panel administrador.
- **Telegram:** un bot creado con BotFather y el Chat ID donde recibirá comprobantes.
- **Correo:** cuenta Resend, API Key, dominio verificado y dirección remitente.
- **Dominio:** opcional, comprado en cualquier registrador y conectado al proveedor de alojamiento.

### Operación y seguridad

- Mantén una sola instancia de la aplicación mientras utilices SQLite.
- Conserva la base en un volumen persistente y realiza respaldos periódicos.
- No subas `.env.local`, `.data` ni claves al repositorio.
- Mantén `COOKIE_SECURE=true` en producción y usa únicamente HTTPS.
- Para varias instancias o crecimiento alto, el siguiente paso es migrar SQLite a PostgreSQL.
