# Changelog

## Unreleased

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
