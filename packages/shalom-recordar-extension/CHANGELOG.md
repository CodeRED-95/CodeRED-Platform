# Changelog

## 2.7.9 - 2026-08-10

### Fixed

- Se restaura la detección de `Clave` usando la lógica original basada en el modal `#modalValidarCodigo` y los inputs `swal-input1..4`.
- `Clave` vuelve a guardarse como `Clave` sin pasar por la clasificación de `DNI`, `CE` o `RUC`.

## 2.7.8 - 2026-08-10

### Changed

- La captura vuelve a depender de los cambios reales de los inputs relevantes en lugar de exigir `Enter`.
- `#inputnombre` recupera la clasificación automática de `DNI`, `CE` y `RUC`.
- `#inputnroguia` queda fijado como `OS` aunque el valor pudiera parecer un documento.
- `Clave` vuelve a capturarse desde su selector original de `swal-input1..4` sin reclasificarse por longitud.

### Fixed

- Se restaura la captura automática por inputs para Shalom Control.
- Se evita que `Clave` y `OS` se pierdan por depender de `Enter`.
- Se mantiene la protección contra duplicados técnicos en una ventana corta.

## 2.7.7 - 2026-08-10

### Changed

- `#inputnroguia` vuelve a capturarse como `OS` y ya no se clasifica por longitud.
- `#inputnombre` queda como único campo que clasifica `DNI`, `CE` y `RUC` por longitud.
- `Clave` se mantiene separada por sus campos originales `swal-input1..4` y se captura solo con `Enter`.

### Fixed

- Se evita que `OS` se interprete como `DNI`.
- Se restaura la prioridad por campo antes que por longitud.

## 2.7.6 - 2026-08-10

### Changed

- La captura continúa ocurriendo únicamente con `Enter`, pero ahora recupera también `Clave` y `OS` además de los documentos numéricos.
- `Clave` vuelve a salir del campo correspondiente sin ser reclasificada como DNI, CE o RUC.
- `OS` vuelve a guardarse desde su campo correspondiente y se conserva como texto.

### Fixed

- Se restaura la detección de `Clave` y `OS` que había en la extensión base.
- Se mantiene la prioridad correcta: campo relevante primero y clasificación por longitud solo para documentos.

## 2.7.5 - 2026-08-10

### Changed

- La captura ahora solo guarda DNI, CE o RUC cuando el usuario presiona `Enter` después de escribir el documento.
- Se elimina la ruta de captura automática por `input`, `change`, `blur`, `keyup` y lectura reactiva del DOM.
- El `MutationObserver` queda solo para detectar campos nuevos y asociar el listener delegado, sin guardar datos por sí mismo.

### Fixed

- Se evitan duplicados técnicos provocados por múltiples eventos del DOM durante una sola acción del usuario.
- Se conservan los ceros iniciales y la clasificación estricta de `DNI`, `CE` y `RUC` como cadenas.

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
