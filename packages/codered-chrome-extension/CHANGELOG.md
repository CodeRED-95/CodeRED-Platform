# Changelog

## Unreleased

- Corrige la inyeccion inmediata del buscador cuando el header ya existe antes de iniciar el content script.
- Agrega soporte confiable para navegacion SPA con `MutationObserver` unico, debounce y reinyeccion cuando Shalom reemplaza el header.
- Amplia los selectores de header alternativos y registra el selector usado para diagnostico.
- Mantiene `platform.codered.host` solo en `host_permissions` y evita inyectar el content script fuera de dominios Shalom.
- Mejora logs `[Shalom Pro]` sin exponer tokens ni datos sensibles.
- Permite que el buscador permanezca visible aun con catalogo local vacio y muestra instrucciones para sincronizar.

## 1.0.0 - 2026-07-30

- Primera version de Buscador Shalom Control.
- Busqueda local offline sobre `chrome.storage.local`.
- Sincronizacion con CodeRED Platform via `/api/v1`.
- Validacion de token, configuracion, solicitud de token y empaquetado ZIP.
- Se agrega content script para Shalom Control en cualquier `*.shalomcontrol.com` y `*.shalom.pe`.
- Se conserva seleccion real de `osProDestino`, eventos `input`/`change` y actualizacion Chosen sin exponer token al content script.
