# Buscador Shalom Control

Extension Chrome Manifest V3 para inyectar un buscador de agencias dentro de Shalom Control y consultar agencias usando CodeRED Platform como unica fuente oficial.

La extension no realiza scraping, no consume GitHub Gist y no usa JSON estatico como fuente principal. Despues de la primera sincronizacion correcta, la busqueda se ejecuta localmente desde `chrome.storage.local` y sigue funcionando sin conexión con la ultima cache valida.

## Version 2.3.0

La version visible de la extension se define en `src/shared/version.ts`. `manifest.json`, `package.json`, `package-lock.json`, el popup y las pruebas deben permanecer alineados en `2.3.0`.

### Popup compacto

El popup v2.3.0 fue reconstruido desde cero como una sola columna de 360 px, tema oscuro CodeRED y altura dependiente del contenido. No contiene buscador de agencias, listado, tarjetas de resultados, paneles informativos extensos, datos tecnicos ni scroll interno.

Datos mostrados:

- Logo y nombre `Buscador Shalom`.
- Version de la extension.
- Estado del token.
- Token enmascarado cuando existe.
- Ultima sincronizacion.
- Agencias disponibles.
- Estado corto de conexion.
- Estado de sincronizacion automatica.

Acciones disponibles:

- `Solicitar token`: abre `https://platform.codered.host/solicitar-token`.
- `Configurar token`: ejecuta `chrome.runtime.openOptionsPage()`.
- `Probar conexión`: disponible solo con token y llama a `API_TEST_CONNECTION`.
- `Solicitar otro token`: enlace discreto disponible solo con token.

Sin token, el popup muestra `Token no configurado`, `Sin sincronizar`, `0` agencias y estado `Desconectado`. Si no puede leer el estado local, mantiene disponibles `Solicitar token` y `Configurar token`.

## Desarrollo

PowerShell:

```powershell
cd E:\Documentos\GitHub\CodeRED-Platform\packages\codered-chrome-extension
Remove-Item -Recurse -Force node_modules -ErrorAction SilentlyContinue
Remove-Item -Force package-lock.json -ErrorAction SilentlyContinue
npm install
npm run lint
npm run typecheck
npm test
npm run build:extension
```

Linux o Docker:

```bash
cd /var/www/html/packages/codered-chrome-extension
rm -rf node_modules package-lock.json
npm install
npm run lint
npm run typecheck
npm test
npm run build:extension
```

## API consumida

- `GET /api/v1/extension/chrome/config`: configuracion publica sin secretos.
- `GET /api/v1/me`: validacion del token.
- `GET /api/v1/catalog/metadata`: revision del catalogo y cursor.
- `GET /api/v1/agencies`: sincronizacion completa paginada.
- `GET /api/v1/agencies/changes`: sincronizacion incremental cuando el cursor sea valido.

Scopes requeridos: `agencias:consultar`, `agencies:read`, `agencies:map`.

## Carga manual en Chrome

1. Ejecutar `npm run build:extension`.
2. Abrir `chrome://extensions`.
3. Activar Modo de desarrollador.
4. Elegir Cargar extension sin empaquetar.
5. Seleccionar `packages/codered-chrome-extension/dist`.
6. Para aplicar cambios posteriores, volver a `chrome://extensions` y pulsar Recargar en la tarjeta de la extension descomprimida.
7. Recargar la pestaña abierta de Shalom Control para que Chrome reinstale el content script en la pagina.

## Build de extension

El build separa tres contextos:

- `popup.html` y `options.html` se compilan como paginas de extension con modulos ES y rutas relativas `./assets/...`.
- `background.js` se compila como service worker modulo y el manifest declara `type: module`.
- `content.js` se compila aparte como IIFE autocontenido, sin `import`, `export`, `import(...)` ni `require(...)`, porque Chrome lo ejecuta como content script clasico.

Usar `npm run build:extension` para compilar y validar `dist`. El validador revisa manifest, archivos declarados, sintaxis de `content.js`, ausencia de imports en el content script, ausencia de rutas absolutas `/assets` en HTML y ausencia de preloads a `modulepreload-polyfill`.

## ZIP publicable

```bash
npm run package
```

El ZIP queda en `packages/codered-chrome-extension/release/buscador-shalom-control-2.3.0.zip`.

## Cache y sincronizacion

La extension conserva la ultima cache funcional ante errores de red, 401, 403 o respuestas vacias inesperadas. El service worker programa `chrome.alarms` cada 24 horas y evita sincronizaciones paralelas. Eliminar el token no borra las agencias cacheadas, pero bloquea nuevas sincronizaciones hasta configurar otro token.

## Storage canonico

La extension usa estas claves canonicas en `chrome.storage.local`:

- `codered_api_token`: token privado, nunca se muestra completo en el popup.
- `codered_token_metadata`: metadata visible, incluido `tokenMasked`.
- `codered_agency_catalog`: catalogo local de agencias.
- `codered_sync_metadata`: revision, cursor, ultima sincronizacion, estado y mensaje.
- `codered_catalog_version`, `codered_last_sync_at`, `codered_last_sync_status`: compatibilidad de metadata sincronizada.

Al iniciar, el storage migra claves antiguas como `auth`, `token`, `apiToken`, `coderedToken`, `accessToken`, `platformToken` y `catalogToken` hacia la clave canonica sin imprimir el secreto.

## CORS

Configurar `API_ALLOWED_ORIGINS` con el origen de la extension si se restringe el entorno productivo. CodeRED expone `ETag` y `Last-Modified` para optimizar sincronizaciones.

## Solucion de problemas

- 401: el token no es valido o vencio.
- 403: el token no tiene permisos para consultar agencias.
- Sin conexion: se usan datos guardados.
- Cero agencias: la sincronizacion se considera invalida y se conserva la cache anterior.
- Popup con `No fue posible leer el estado local`: revisar permisos `storage`, service worker en `chrome://extensions` y recargar la extension.
- Content script no aparece: recargar la pestaña de Shalom Control despues de recargar la extension.

## Dominios compatibles

La extension inyecta el buscador solamente en:

- `https://shalom.pe/*`
- `https://*.shalom.pe/*`
- `https://shalomcontrol.com/*`
- `https://*.shalomcontrol.com/*`

`https://platform.codered.host/*` permanece en `host_permissions` para que el service worker sincronice con CodeRED Platform, pero no esta en `content_scripts.matches` y no recibe el buscador.

## Integracion con Shalom Control

La extension se inyecta automaticamente en `shalomcontrol.com`, cualquier `*.shalomcontrol.com`, `shalom.pe` y cualquier `*.shalom.pe`. `sysprovincia2` y `syslima` son ejemplos, no una lista cerrada.

El content script intenta inyectar inmediatamente al iniciar y luego observa cambios SPA con `MutationObserver` y debounce. Busca encabezados compatibles como `.mdl-layout__header-row`, `header .mdl-layout__header-row`, `.mdl-layout__header`, `header`, `[role="banner"]`, `.navbar`, `.topbar` y `.header`; inyecta una sola vez `#mi-buscador-contenedor` y lo reinyecta si Shalom reemplaza el encabezado.

Detecta Terrestre/Aereo mediante `title`, texto visible normalizado, `onclick`, clases activas y `aria-selected`. El canal interno se normaliza a `TERRESTRE` o `AEREO`.

Para seleccionar destino localiza `select[id*="osProDestino"]`, prioriza selectores visibles y habilitados, rechaza multiples candidatos activos, asigna el valor real del `select`, dispara `input` y `change`, y actualiza el DOM de Chosen cuando existe. Si una agencia de CodeRED no esta en el selector actual, no cambia el formulario.

El content script no recibe el token. Usa mensajes `CATALOG_GET`, `CATALOG_STATUS` y `CATALOG_SYNC` contra el service worker, que es el unico componente que llama a CodeRED Platform con Bearer token.

## Diagnostico del content script

En la consola de la pagina Shalom deben aparecer logs con prefijo `[Shalom Pro]`, por ejemplo `Content script iniciado`, `Dominio permitido`, `Target encontrado con selector: ...` y `Buscador inyectado`. Para comprobar la inyeccion desde DevTools ejecutar:

```js
document.getElementById('mi-buscador-contenedor')
```

Si devuelve `null`, revisar la consola de la pagina. Si hay errores del service worker, abrir `chrome://extensions`, buscar la extension y entrar a Inspeccionar vistas/service worker.

## Catalogo vacio

El buscador se muestra aunque `chrome.storage.local` no tenga agencias. En ese estado, al escribir muestra: `No hay agencias sincronizadas. Abre la configuracion y pulsa Sincronizar ahora`. Abrir Opciones de la extension, guardar un token valido si falta, pulsar Sincronizar ahora y recargar o volver a escribir en el buscador; el content script escucha cambios de `chrome.storage.local` y recarga el catalogo sin reinstalar la extension.

## Dependencias de pruebas

La version 2.3.0 fija `chai` en `5.2.1` mediante `overrides` para evitar la resolucion defectuosa `chai@5.3.3 -> pathval@^2.1.0`, ya que `pathval` solo publica hasta `2.0.1`. Vitest permanece en la linea 3.2.x y sigue siendo el runner de pruebas compatible con Vite 6.
