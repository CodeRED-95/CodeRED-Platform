# Changelog

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
