# Changelog

## 1.0.0 - 2026-07-30

- Primera version de Buscador Shalom Control.
- Busqueda local offline sobre `chrome.storage.local`.
- Sincronizacion con CodeRED Platform via `/api/v1`.
- Validacion de token, configuracion, solicitud de token y empaquetado ZIP.
- Se agrega content script para Shalom Control en cualquier `*.shalomcontrol.com` y `*.shalom.pe`.
- Se conserva seleccion real de `osProDestino`, eventos `input`/`change` y actualizacion Chosen sin exponer token al content script.
