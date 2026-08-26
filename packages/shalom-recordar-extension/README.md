# Shalom Recordar Extension

Extensión Chrome para capturar y sincronizar datos de Shalom Recordar con CodeRED Platform.

## Qué hace

- Captura documentos numéricos válidos desde Shalom Control.
- Clasifica automáticamente:
  - `DNI` = 8 dígitos
  - `CE` = 9 dígitos
  - `RUC` = 11 dígitos
- Captura `OS` al cambiar su input y la `Clave` cuando el servidor confirma que el codigo fue correcto (aparece el formulario de entrega o de comprobante).
- Registra los cambios reales de los inputs relevantes sin depender de `Enter`.
- Ignora valores inválidos o de longitud no permitida.
- Guarda historial local compacto.
- Sincroniza los datos con CodeRED Platform mediante login de usuario.

## Requisitos

- Node.js compatible con npm.
- Chrome o Chromium con soporte para extensiones Manifest V3.
- Acceso a `https://platform.codered.lat`.

## Instalación

```bash
cd /var/www/html/packages/shalom-recordar-extension
rm -rf node_modules package-lock.json
npm install
```

## Scripts

```bash
npm run lint
npm run typecheck
npm test
npm run build:extension
npm run package
```

## Flujo de desarrollo

### Lint

```bash
npm run lint
```

### Typecheck

```bash
npm run typecheck
```

### Tests

```bash
npm test
```

### Build

```bash
npm run build:extension
```

### Empaquetado ZIP

```bash
npm run package
```

El ZIP se genera como `release/shalom-recordar-extension-<version>.zip`, tomando la versión desde `package.json`.

## Carga manual en Chrome

1. Ejecuta `npm run build:extension`.
2. Abre `chrome://extensions`.
3. Activa el modo de desarrollador.
4. Usa `Cargar descomprimida`.
5. Selecciona `packages/shalom-recordar-extension/dist`.

## Login con CodeRED Platform

La extensión muestra correo y contraseña.
Al iniciar sesión:

- CodeRED Platform valida las credenciales.
- Se emite un token por instalación.
- El token queda guardado en `chrome.storage.local`.
- La contraseña no se almacena.

## Sincronización

- La sincronización usa `Authorization: Bearer ...`.
- El historial local se envía deduplicado.
- Los errores 401, 403, 422, 429 y 5xx se muestran con mensajes diferenciados.
- La sincronización automática diaria se ejecuta a las 08:00 AM en `America/Lima`.
- Si Chrome estaba cerrado o suspendido a esa hora, la extensión recupera la ejecución en la siguiente apertura.
- La sincronización automática solo corre con sesión válida, token activo e `installation_uuid`.

## Captura de datos

La captura ocurre automáticamente cuando cambian los inputs relevantes.

Tipos capturados:

- `#inputnombre`:
  - 8 dígitos: `DNI`
  - 9 dígitos: `CE`
  - 11 dígitos: `RUC`
- `#inputnroguia`:
  - `OS`
  - origen: `#inputnroguia`
  - solo números
  - máximo 8 dígitos
  - captura automática del valor final mediante debounce
- `Clave`: se teclea en las casillas `swal-input1..4`, que van llenando un buffer. La clave se envia (tomada del buffer) cuando el servidor confirma que el codigo fue correcto, es decir cuando aparece uno de los formularios de confirmacion:
  - `frmEntrega` (`action .../entrega/ajax`): registro de la entrega.
  - `formPagoOS` (`action .../pagos/Generar`): ventana "Comprobante".
  El cierre del modal de validacion NO se usa: tambien ocurre al cancelar o fallar. Como el buffer conserva lo ultimo tecleado, si hubo intentos fallidos se envia el intento acertado.

Valores con letras, espacios internos, longitudes distintas o caracteres inválidos se ignoran.
La protección contra duplicados técnicos evita guardar varias veces el mismo valor por una sola interacción rápida del DOM.

## Historial local

El popup muestra los últimos 20 registros locales, ordenados de más reciente a más antiguo.
El historial conserva fecha, tipo y valor.

## Sesión

- `chrome.storage.local` guarda el token y la metadata de usuario.
- `chrome.storage.session` se usa para la clave de cifrado de sesión.
- Cerrar sesión elimina solo credenciales, no el historial local.

## Versión

La fuente única de verdad es `package.json`.
`manifest.json` se sincroniza automáticamente durante `npm run build`.

## Distribución e instalación

La extension captura la clave de entrega, asi que **no puede publicarse en la Chrome Web Store**. Segun como esten gestionados los equipos:

- **Equipos gestionados** (dominio o Chrome Browser Cloud Management): instalacion forzada por politica desde un `.crx` firmado autoalojado. Ver `docs/instalacion-empresa.md`.
- **Equipos NO gestionados** (un Windows normal): Chrome/Edge ignoran el force-install autoalojado por seguridad, asi que se carga **sin empaquetar** desde una carpeta fija, con un instalador que ademas programa la **auto-actualizacion** (Tarea Programada o carpeta Inicio). Ver `docs/instalacion-sin-empaquetar.md`.

El empaquetado se genera con:

```bash
npm run pack:crx
```

que produce en `release/`: el `.crx` firmado, `updates.xml`, `latest.json`, el zip para cargar sin empaquetar, el instalador interactivo por navegador (`Instalar-Shalom-Recordar.cmd`), el instalador con auto-update (`Instalar-Desempaquetada.cmd`) y los `.reg` por navegador; y publica el `.crx`, el zip y los manifiestos en `public/ext/shalom-recordar/` de la Plataforma.

La clave de firma vive en `packaging/` (fuera de git) y es estable: de ella se deriva el id de la extension.

## Estructura básica

- `content.js`: captura en Shalom Control (documentos, OS y clave por formularios de confirmacion).
- `background.js`: cola, almacenamiento y sincronización.
- `sync.js`: login, estado, sincronización y exportación.
- `popup.html` / `popup.js`: interfaz compacta.
- `crypto.js`: cifrado local.
- `db.js`: almacenamiento IndexedDB.
- `scripts/`: build, validación, sincronización de versión, empaquetado y firma `.crx` (`pack-crx.mjs`) con las plantillas de instalador en `scripts/templates/`.
- `tests/`: pruebas rápidas de captura y sesión.
