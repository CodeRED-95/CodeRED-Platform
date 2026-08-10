# Changelog

## 2.7.4 - 2026-08-10

### Added

- README de la extensión con instalación, build, package, login y sincronización.
- Tooling equivalente al sistema de desarrollo de la extensión base: lint, typecheck, tests, build, validación y empaquetado.

### Changed

- `package.json` pasa a ser la fuente única de verdad para la versión.
- `manifest.json` se sincroniza automáticamente durante el build.
- El ZIP publicable usa el formato `shalom-recordar-extension-<version>.zip`.

### Fixed

- La captura de datos deja de guardar valores genéricos cuando el número no es un DNI, CE o RUC válido.
- Se evita la duplicación técnica en la captura por eventos repetidos o reinicializaciones del content script.
- El historial local y la sincronización conservan `DNI`, `CE` y `RUC` correctamente.

### Security

- No se incluyen secretos, `.env`, tests ni `node_modules` en el ZIP publicable.
- La versión visible de la extensión se deriva de una única fuente de verdad.

## 2.7.3 - 2026-08-10

### Fixed

- La extensión clasificaba documentos y evitaba capturas genéricas como `inputnombre`.
- Se redujeron duplicados técnicos por eventos repetidos en una ventana corta.

## 2.7.2 - 2026-08-10

### Fixed

- La extensión conservaba el tipo semántico al sincronizar y mejoraba el popup compacto.

