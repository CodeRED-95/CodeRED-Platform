# Changelog

## 2.9.5 - 2026-08-26

### Changed

- El documento del campo de busqueda (DNI/RUC/CE/PS) ahora se captura tras una breve pausa al teclear (debounce de 650 ms), no en cada pulsacion. Antes se enviaba un registro por cada tecla; ahora se guarda una sola vez el numero completo. Mismo criterio que ya tenia la OS. Al perder el foco (change) se captura de inmediato.


## 2.9.4 - 2026-08-26

### Changed

- El tipo de documento en el campo de busqueda (`inputnombre`) ahora se toma del desplegable `tipodoclist` (DNI/RUC/CE/PS), que es lo que el usuario declara, en vez de adivinarlo por la cantidad de digitos. La longitud queda solo de respaldo cuando el desplegable no esta disponible.

### Fixed

- El **pasaporte (PS)** ya se captura. Antes la clasificacion exigia solo digitos con longitud 8/9/11, asi que un pasaporte -alfanumerico y de longitud variable- se descartaba en silencio.
- Se elimina la confusion por longitud: un CE de 8 digitos ya no se guarda como DNI, ni un valor de 11 digitos se fuerza a RUC. El tipo capturado coincide con el seleccionado.


## 2.9.3 - 2026-08-26

### Fixed

- La clave "a veces dejaba de capturarse". `checkEntregaConfirmation` marcaba el latch `entregaConfirmed` ANTES de leer el buffer: si un modal de confirmacion (`frmEntrega`/`formPagoOS`) pasaba el chequeo de visibilidad con el buffer aun vacio -durante la animacion fade de Bootstrap, o con otra operacion en curso-, quedaba latcheado y la extension no reintentaba aunque la clave se tecleara despues, hasta que el modal se ocultara del todo. Ahora el latch se activa solo cuando de verdad se capturo una clave no vacia, asi que un formulario falso-visible con buffer vacio ya no bloquea la captura siguiente.
- La heuristica de visibilidad (`isElementVisible`) solo miraba el `style` inline y `aria-hidden`, no el `display:none` que Bootstrap aplica desde la clase `.modal`. Daba falsos positivos de "visible" con el modal cerrado. Ahora comprueba tambien `display`/`visibility` calculados con `getComputedStyle` (con reserva al chequeo por atributos donde no existe, como en el DOM simulado de las pruebas).


## 2.9.2 - 2026-08-26

### Fixed

- `content.js` lanzaba `Uncaught TypeError: Cannot read properties of undefined (reading 'sendMessage')` en cada pulsacion despues de recargar o actualizar la extension: el content script antiguo sigue vivo en la pagina pero su `chrome.runtime` queda invalidado. Ahora se comprueba el contexto antes de enviar, el envio va protegido, y el script se desmonta (listeners, observador y temporizadores) cuando el contexto ya no es valido.
- `chrome.runtime.sendMessage` se llama con callback para consumir `chrome.runtime.lastError`, que dejaba errores no capturados en consola cuando el service worker estaba dormido o no habia receptor.
- `npm run package` fallaba con exit 1 en Windows: `scripts/package-extension.mjs` invocaba el binario `zip`, inexistente ahi, y se tragaba el ENOENT sin mensaje. Ahora el ZIP se construye en Node (`scripts/zip.mjs`), modulo que comparte con `pack-crx.mjs` en lugar de duplicar la implementacion.

## 2.9.1 - 2026-08-26

### Changed

- La clave se considera correcta tambien cuando aparece la ventana "Comprobante" (`formPagoOS`, `action .../pagos/Generar`), ademas del formulario de entrega. Cualquiera de los dos confirma que el codigo fue valido.

## 2.9.0 - 2026-08-25

### Changed

- La clave se considera correcta y se captura cuando aparece el formulario de entrega (`frmEntrega`, `action="entrega/ajax"`), que el servidor inyecta solo tras validar el código. Antes se disparaba con el cierre del modal de validación, que también ocurre al cancelar o fallar, de modo que podía capturarse un código erróneo. Si hubo intentos fallidos, se envía el intento acertado (el último del buffer).

## 2.8.1 - 2026-08-25

### Fixed

- La clave se capturaba incorrecta: al cerrarse el modal de validación se leía el valor de las casillas `swal-input*` del DOM, pero SweetAlert ya las había reseteado en ese instante. Ahora la clave se arma únicamente desde el buffer de lo tecleado, como en la versión original, conservando la detección por el cambio de `display` del modal.
- El buffer de la clave se indexa con la id normalizada, coherente con `CLAVE_FIELDS`.
- La recuperación de sincronización tras iniciar Chrome descartaba la fecha inyectada por un `instanceof Date` que falla entre realms; se comprueba por forma. Corrige un test dependiente de la fecha del sistema.

## 2.8.0 - 2026-08-11

### Added

- Sincronización automática diaria a las 08:00 AM de `America/Lima` con recuperación en la siguiente apertura de Chrome si la alarma no pudo ejecutarse.

### Changed

- El popup muestra la última sincronización automática y la próxima ventana programada.

### Fixed

- La sincronización automática solo se marca como completada después de un envío exitoso.
- Se evita duplicar la sincronización automática del mismo día incluso si el popup, el startup y la alarma coinciden.

## 2.7.16 - 2026-08-10

### Fixed

- `Clave` vuelve a confirmarse desde el buffer del modal al cerrarse, evitando que se guarden dígitos sueltos.
- Se preserva la reconstrucción completa de claves como `3535` y `0123`.

## 2.7.15 - 2026-08-10

### Changed

- Se rehace el paquete de la extensión con la versión sincronizada desde la fuente única y el lockfile regenerado por una instalación limpia.

## 2.7.14 - 2026-08-10

### Fixed

- `Clave` se confirma una sola vez aunque el modal dispare tanto el debounce como el cierre.
- Se evita volver a guardar la misma clave completa cuando el debounce ya se ejecutó.

## 2.7.13 - 2026-08-10

### Fixed

- `Clave` vuelve a guardarse como un único valor completo desde el modal, sin fragmentarse en registros por dígito.
- Se conserva el valor completo de claves como `57`, `3535` y `0123`.

## 2.7.12 - 2026-08-10

### Fixed

- `Clave` deja de fragmentarse en capturas parciales al escribir dígito por dígito.
- Se aumenta el debounce de `Clave` para guardar solo el valor final completo del input.

## 2.7.11 - 2026-08-10

### Fixed

- `Clave` deja de perder el primer dígito al capturarse desde el valor real actualizado del input con debounce corto.
- `Clave` conserva valores como `3535` y `0123` completos, sin reconstrucción parcial.

## 2.7.10 - 2026-08-10

### Fixed

- Se corrige la captura progresiva de `OS` en `#inputnroguia` usando debounce por campo.
- `OS` vuelve a guardar solo el valor final, sin registrar estados intermedios como `8`, `89` o `89906`.

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
