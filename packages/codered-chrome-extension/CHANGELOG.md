# Changelog

## 2.3.4 - 2026-08-10

- Trata `https://*.shalomcontrol.com/listaordenservicio` como una ruta neutral cuando el canal no puede determinarse con evidencia suficiente.
- El buscador deja de bloquearse en espera de canal en esa ruta y busca en todas las agencias públicas disponibles.
- La selección de agencias prueba una estrategia neutral compatible cuando el canal sigue desconocido en esa pantalla.
- Elimina los warnings y bloqueos de canal ambiguo o no confirmado para ese caso concreto, sin tocar las rutas donde el canal sí se detecta.

## 2.3.3 - 2026-08-10

- Corrige el empaquetado para que el ZIP tome siempre la version real desde `package.json`.
- Sincroniza automaticamente `manifest.json` y `src/shared/version.ts` antes del build.
- Actualiza la documentacion y las pruebas para validar la version unica de verdad.

## 2.3.2 - 2026-08-10

- Trata la seleccion imposible de una agencia como una condicion esperada cuando Shalom Control no la expone temporalmente.
- Sustituye el warning por un mensaje visual discreto y un `console.info` deduplicado para no ensuciar `chrome://extensions`.
- Mantiene los errores tecnicos reales en `console.warn` con contexto estructurado y sin `[object Object]`.

## 2.3.1 - 2026-08-10

- Corrige la deteccion del canal activo en Shalom Control para no forzar TERRESTRE mientras el DOM sigue cargando.
- Deduplica los warnings de canal y de seleccion fallida para evitar ruido repetido en la consola.
- Mejora el flujo de seleccion de agencias con mensajes estructurados y sin concatenar objetos.
- Sube la version visible de la extension a `2.3.1`.

## 2.3.0 - 2026-08-02

- Reconstruye el popup compacto desde cero con una sola columna de 360 px, tema oscuro CodeRED, sin scroll interno y sin layout de dos columnas.
- Elimina del popup el panel `¿Qué puedes hacer?`, tarjetas informativas extensas, version local larga, buscador, listado de agencias y resultados.
- Mantiene solo estado de token, token enmascarado, ultima sincronizacion, agencias disponibles, estado de conexion, sincronizacion automatica y acciones minimas.
- Conserva `GET_STATE`, `API_TEST_CONNECTION`, solicitud publica de token y apertura de Options sin exponer tokens completos.
- Agrega reaccion a cambios de `chrome.storage.local` y estado de error local usable sin conexion.
- Actualiza version visible, manifest, package, package-lock, README y pruebas a `2.3.0`.

## 2.2.1 - 2026-08-02

- Corrige la instalacion limpia de dependencias fijando `chai` en `5.2.1` mediante `overrides`, evitando la cadena incompatible `chai@5.3.3 -> pathval@^2.1.0`.
- Mantiene Vitest 3.2.x, Vite 6 y el build IIFE del content script sin cambios funcionales.
- Actualiza la version visible de la extension a `2.2.1`.


## Unreleased

## 2.2.0 - 2026-08-02

- Alinea la versión de la extensión con CodeRED Platform 2.2.0.
- No cambia el content script, la búsqueda inyectada, Chosen ni la sincronización del catálogo.

## 2.1.1 - 2026-08-02

- Rediseña el popup con tema oscuro, ancho responsive de 560 a 720 px, header con version visible, cierre accesible, layout de dos columnas y ajuste sin barra de scroll para 1280x800.
- Optimiza tipografia y espaciado: titulo 18px, secciones 11px, texto base 12px y tarjetas compactas sin cortar informacion.
- Mantiene en el popup solo estado del token, sincronizacion, agencias disponibles, version local, solicitar token y configurar token.
- Agrega accion discreta de informacion y conserva intacto el content script inyectado, la seleccion Chosen y la sincronizacion del catalogo.

## 2.1.0 - 2026-08-02

- Simplifica el popup para mostrar solo estado y gestión del token; elimina búsqueda, tarjetas y acciones de agencias del popup.
- Unifica el token en `chrome.storage.local` bajo `codered_api_token` y migra claves legacy como `auth`, `apiToken`, `coderedToken`, `platformToken` y `catalogToken`.
- Cambia la solicitud de token para abrir la página pública `https://platform.codered.host/solicitar-token` sin enviar secretos desde la extensión.
- Asegura que el estado `updating` de sincronización termine en estado final `updated`, `unchanged`, `error`, `token_expired` o `forbidden`.
- Agrega `src/shared/version.ts` como fuente de version `2.1.0` usada por popup y pruebas.
- Restaura el diseno oscuro del buscador inyectado con barra roja, panel flotante y grilla responsive de tres columnas.
- Elimina el selector interno Auto/Terrestre/Aereo y detecta el canal desde los botones reales de Shalom Control.
- Mejora la deteccion del Chosen activo para no modificar selectores ocultos o ambiguos.
- Renderiza tarjetas completas con estado, servicio, categoria real, CO, capacidades, ubicacion, direccion y avisos sin inventar datos.
- Corrige la inyeccion inmediata del buscador cuando el header ya existe antes de iniciar el content script.
- Agrega soporte confiable para navegacion SPA con `MutationObserver` unico, debounce y reinyeccion cuando Shalom reemplaza el header.
- Amplia los selectores de header alternativos y registra el selector usado para diagnostico.
- Mantiene `platform.codered.host` solo en `host_permissions` y evita inyectar el content script fuera de dominios Shalom.
- Mejora logs `[Shalom Pro]` sin exponer tokens ni datos sensibles.
- Permite que el buscador permanezca visible aun con catalogo local vacio y muestra instrucciones para sincronizar.
- Separa el build del content script como IIFE autocontenido para evitar `Cannot use import statement outside a module`.
- Genera `content.js` y `background.js` con nombres estables en el manifest final.
- Elimina preloads innecesarios y rutas absolutas `/assets` en popup/options.
- Agrega `scripts/validate-extension-build.mjs` y `npm run build:extension` para validar automaticamente `dist`.

## 1.0.0 - 2026-07-30

- Primera version de Buscador Shalom Control.
- Busqueda local offline sobre `chrome.storage.local`.
- Sincronizacion con CodeRED Platform via `/api/v1`.
- Validacion de token, configuracion, solicitud de token y empaquetado ZIP.
- Se agrega content script para Shalom Control en cualquier `*.shalomcontrol.com` y `*.shalom.pe`.
- Se conserva seleccion real de `osProDestino`, eventos `input`/`change` y actualizacion Chosen sin exponer token al content script.
