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


## Diseño inyectado

El buscador integrado en Shalom Control usa la experiencia visual recuperada de la extension anterior: barra oscura con borde rojo redondeado, indicador automatico de canal, panel flotante oscuro, scroll interno y grilla responsive de tarjetas. En escritorio la grilla usa tres columnas; en tablet dos; en movil una.

Cada tarjeta muestra los campos disponibles del catalogo normalizado: nombre, codigo, nombre anterior si existe, estado, servicio disponible, categoria real o `Sin categoria`, Centro de Operaciones, capacidad de envio/recepcion, Departamento / Provincia / Distrito, direccion, referencia y avisos de traslado o cierre. No se inventa `Mediana` ni ningun valor por defecto de negocio. El boton MAPA abre una pestaña nueva con `target="_blank"` y no selecciona la agencia.

## Canal y Chosen

La extension no muestra selector interno Terrestre/Aereo ni modo Auto. Detecta el canal activo leyendo los botones reales de Shalom Control mediante `aria-selected`, clases activas, `title`, `aria-label`, `onclick`, texto visible e indicios de camion/avion. El indicador de la barra muestra `Terrestre` o `Aereo` y se actualiza al cambiar de segmento.

Al seleccionar una tarjeta, el content script vuelve a detectar el canal activo, busca el `select[id*="osProDestino"]` asociado al Chosen visible del panel activo y descarta selectores deshabilitados, ocultos o ambiguos. Para Terrestre usa solo `terrestrialText`; para Aereo usa solo `airText`. Luego selecciona la opcion por coincidencia exacta, normalizada o identificador externo no ambiguo, emite `input` y `change`, dispara `chosen:updated` si jQuery existe y actualiza `.chosen-single span`.

Si no encuentra un Chosen activo, la barra no queda ocupada con "No hay selector". El error breve se muestra solo al intentar seleccionar y el diagnostico detallado aparece en consola con prefijo `[CodeRED Shalom]`.

## Diagnostico del content script

En la consola de la pagina Shalom deben aparecer logs con prefijo `[Shalom Pro]`, por ejemplo `Content script iniciado`, `Dominio permitido`, `Target encontrado con selector: ...` y `Buscador inyectado`. Para comprobar la inyeccion desde DevTools ejecutar:

```js
document.getElementById('mi-buscador-contenedor')
```

Si devuelve `null`, revisar la consola de la pagina. Si hay errores del service worker, abrir `chrome://extensions`, buscar la extension y entrar a Inspeccionar vistas/service worker.

## Catalogo vacio

El buscador se muestra aunque `chrome.storage.local` no tenga agencias. En ese estado, al escribir muestra: `No hay agencias sincronizadas. Abre la configuracion y pulsa Sincronizar ahora`. Abrir Opciones de la extension, guardar un token valido si falta, pulsar Sincronizar ahora y recargar o volver a escribir en el buscador; el content script escucha cambios de `chrome.storage.local` y recarga el catalogo sin reinstalar la extension.
