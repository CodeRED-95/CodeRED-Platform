# Buscador Shalom Control

Extension Chrome Manifest V3 para inyectar un buscador de agencias dentro de Shalom Control y consultar agencias usando CodeRED Platform como unica fuente oficial.

La extension no realiza scraping, no consume GitHub Gist y no usa JSON estatico como fuente principal. Despues de la primera sincronizacion correcta, la busqueda se ejecuta localmente desde `chrome.storage.local`.

## Desarrollo

```bash
cd packages/codered-chrome-extension
npm install
npm run lint
npm run typecheck
npm test
npm run build
```

## API consumida

- `GET /api/v1/extension/chrome/config`: configuracion publica sin secretos.
- `GET /api/v1/me`: validacion del token.
- `GET /api/v1/catalog/metadata`: revision del catalogo y cursor.
- `GET /api/v1/agencies`: sincronizacion completa paginada.
- `GET /api/v1/agencies/changes`: sincronizacion incremental cuando el cursor sea valido.

Scopes requeridos: `agencias:consultar`, `agencies:read`, `agencies:map`.

## Carga manual en Chrome

1. Ejecutar `npm run build`.
2. Abrir `chrome://extensions`.
3. Activar Modo de desarrollador.
4. Elegir Cargar extension sin empaquetar.
5. Seleccionar `packages/codered-chrome-extension/dist`.
6. Para aplicar cambios posteriores, volver a `chrome://extensions` y pulsar Recargar en la tarjeta de la extension descomprimida.
7. Recargar la pestaña abierta de Shalom Control para que Chrome reinstale el content script en la pagina.

## ZIP publicable

```bash
npm run package
```

El ZIP queda en `packages/codered-chrome-extension/release/buscador-shalom-control-1.0.0.zip`.

## Cache y sincronizacion

La extension conserva la ultima cache funcional ante errores de red, 401, 403 o respuestas vacias inesperadas. El service worker programa `chrome.alarms` cada 24 horas y evita sincronizaciones paralelas. Eliminar el token no borra las agencias cacheadas, pero bloquea nuevas sincronizaciones hasta configurar otro token.

## CORS

Configurar `API_ALLOWED_ORIGINS` con el origen de la extension si se restringe el entorno productivo. CodeRED expone `ETag` y `Last-Modified` para optimizar sincronizaciones.

## Errores frecuentes

- 401: el token no es valido o vencio.
- 403: el token no tiene permisos para consultar agencias.
- Sin conexion: se usan datos guardados.
- Cero agencias: la sincronizacion se considera invalida y se conserva la cache anterior.

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

Para probar un nuevo subdominio, carga `dist/`, abre la pagina HTTPS de Shalom Control, confirma que aparece el buscador en el encabezado, cambia Terrestre/Aereo, selecciona una agencia y verifica que el `select[id*="osProDestino"]` real cambie. Si no aparece, revisar que el hostname termine exactamente en `.shalomcontrol.com` o `.shalom.pe` y que la pagina tenga un encabezado compatible.

## Diagnostico del content script

En la consola de la pagina Shalom deben aparecer logs con prefijo `[Shalom Pro]`, por ejemplo `Content script iniciado`, `Dominio permitido`, `Target encontrado con selector: ...` y `Buscador inyectado`. Para comprobar la inyeccion desde DevTools ejecutar:

```js
document.getElementById('mi-buscador-contenedor')
```

Si devuelve `null`, revisar la consola de la pagina. Si hay errores del service worker, abrir `chrome://extensions`, buscar la extension y entrar a Inspeccionar vistas/service worker.

## Catalogo vacio

El buscador se muestra aunque `chrome.storage.local` no tenga agencias. En ese estado, al escribir muestra: `No hay agencias sincronizadas. Abre la configuracion y pulsa Sincronizar ahora`. Abrir Opciones de la extension, guardar un token valido si falta, pulsar Sincronizar ahora y recargar o volver a escribir en el buscador; el content script escucha cambios de `chrome.storage.local` y recarga el catalogo sin reinstalar la extension.
