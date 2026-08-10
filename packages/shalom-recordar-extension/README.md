# Shalom Recordar Extension

Extensión Chrome para capturar y sincronizar datos de Shalom Recordar con CodeRED Platform.

## Qué hace

- Captura documentos numéricos válidos desde Shalom Control.
- Clasifica automáticamente:
  - `DNI` = 8 dígitos
  - `CE` = 9 dígitos
  - `RUC` = 11 dígitos
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

## Captura de datos

Solo se guardan registros cuando el valor contiene exactamente:

- 8 dígitos: `DNI`
- 9 dígitos: `CE`
- 11 dígitos: `RUC`

Valores con letras, espacios internos, longitudes distintas o caracteres inválidos se ignoran.

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

## Estructura básica

- `content.js`: captura en Shalom Control.
- `background.js`: cola, almacenamiento y sincronización.
- `sync.js`: login, estado, sincronización y exportación.
- `popup.html` / `popup.js`: interfaz compacta.
- `crypto.js`: cifrado local.
- `db.js`: almacenamiento IndexedDB.
- `scripts/`: build, validación, sincronización de versión y empaquetado.
- `tests/`: pruebas rápidas de captura y sesión.
