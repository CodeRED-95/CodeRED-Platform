# Changelog

## 2.9.0 - 2026-08-25

- Los tres enlaces del pie del popup dejan de estar deshabilitados: Ayuda y Privacidad abren las paginas publicas de CodeRED Platform, y Acerca de muestra version, plataforma, agencias en cache y ultima sincronizacion sin salir del popup.

## 2.8.1 - 2026-08-25

- El content script deja de escribir en la consola de la pagina durante su funcionamiento normal. "Inyeccion omitida" y el resto de mensajes de diagnostico pasan a `console.debug`, oculto salvo que se active el nivel Verbose.
- "Inyeccion omitida" ademas se registra una sola vez por ruta, en vez de en cada comprobacion.

## 2.8.0 - 2026-08-25

- El control horario pasa a ser opt-in por token: solo se aplica en las instalaciones cuyo token lleva la ability `extension:blocking`, que se concede desde el panel de tokens de CodeRED Platform.
- Sin esa ability, la tarjeta de control desaparece del popup y no se aplica ningun bloqueo.
- Se elimina el horario de respaldo escrito en el codigo: sin reglas publicadas no hay bloqueo, tampoco en una instalacion recien puesta.

## 2.7.3 - 2026-08-25

- El aviso "La extension se actualizo: recarga la pagina" deja de salir como advertencia en la consola de la pagina; pasa a `console.debug`.
- Un error inesperado al evaluar el horario se registra una sola vez, no una vez por segundo.

## 2.7.2 - 2026-08-25

- Los fallos al seleccionar agencia dejan de aparecer como avisos en la consola de la pagina: el motivo ya se muestra en el panel del buscador. Ahora salen por `console.debug`, oculto salvo que se active el nivel Verbose, y con el contexto serializado en vez de "[object Object]".

## 2.7.1 - 2026-08-25

- La insignia del buscador solo muestra "Terrestre" o "Aereo". Desaparecen "Modo neutral" y "Canal pendiente": cuando no hay canal, la insignia se oculta.

## 2.7.0 - 2026-08-25

- El buscador funciona en `sysnewos.shalomcontrol.com/service-order/items`: se inyecta en la tarjeta "Agencia de destino" y selecciona el destino en el combobox Vue de la SPA nueva.
- Adaptador nuevo (`destination-combobox.ts`) para ese selector: la lista se teletransporta al body, el commit va por `mousedown` (no `click`) y el filtro del sitio solo compara por departamento, provincia y distrito, nunca por el nombre de la agencia. La extension traduce la agencia elegida a la consulta que ese filtro entiende y pulsa la opcion por su `data-key`, que coincide con nuestro `external_id`.
- No se vuelve a pulsar una agencia ya seleccionada: el sitio la deseleccionaria.
- El canal se lee de los radios Terrestre/Aereo de la SPA.
- `/ordenservicio/listar` y el resto de rutas siguen exactamente igual, con el `<select>` + Chosen de siempre.

## 2.6.3 - 2026-08-25

- La tarjeta de bloqueo se monta en un shadow root: las hojas de estilo del sitio ya no pueden descolocarla. El icono del candado se salia de su circulo en las paginas de sysprovincia2.
- Deja de inyectarse la hoja de estilos del overlay en el head de la pagina.
- `destroy()` limpia tambien el temporizador de cambio de ruta y sus listeners, que quedaban vivos.

## 2.6.2 - 2026-08-25

- Protege tambien el desbloqueo forzoso frente a un contexto de extension invalidado: era la ultima via que podia lanzar "Extension context invalidated" sin recogerlo.

## 2.6.1 - 2026-08-25

- Corrige el error "Extension context invalidated" que se repetia cada segundo en las pestanas abiertas despues de actualizar la extension.
- El control de horario detecta que perdio el canal con la extension y se apaga en silencio, dejando el overlay puesto: una actualizacion no puede servir para saltarse el bloqueo. Basta recargar la pagina para reactivarlo.

## 2.6.0 - 2026-08-25

- Cada destino de una regla lleva su propia ruta: `sysnewos.shalomcontrol.com/service-order` y `sysprovincia2.shalomcontrol.com/ordenservicio/listar` conviven en la misma regla y comparten horario.
- En el panel se puede pegar la URL completa tal cual sale del navegador; un destino sin ruta hereda la ruta por defecto de la regla.
- El payload publica `destinations`; `host_patterns` y `host_pattern` siguen ahi para las instalaciones 2.4.0 y 2.5.0 que aun no se han actualizado.

## 2.5.0 - 2026-08-25

- Una regla puede cubrir varios dominios: el panel acepta una lista y basta con que uno coincida para aplicar el horario.
- El payload publica `host_patterns` (lista) y mantiene `host_pattern` con el primer dominio, para que las instalaciones 2.4.0 sigan bloqueando mientras se actualizan.

## 2.4.0 - 2026-08-24

- El horario de bloqueo deja de estar escrito en el codigo: ahora lo define el panel `Configuracion > Bloqueo extension` de CodeRED Platform y se descarga desde `GET /api/v1/extension/chrome/block-rules`.
- Soporta varias reglas, horarios distintos por dia (por ejemplo lunes a sabado 08:00-20:05 y domingo 08:00-17:05), zona horaria configurable y modo "horario permitido" u "horario bloqueado".
- Las reglas se refrescan al arrancar el navegador, cada 30 minutos y en cada sincronizacion manual; si no hay conexion se conserva la ultima copia local y, sin ninguna, se aplica el horario historico de Service Order.
- El content script cubre todo `*.shalomcontrol.com` para poder bloquear cualquier ruta que configure el panel; el buscador de agencias sigue limitado a las rutas de siempre.
- El popup muestra el nombre, el horario y la zona horaria de la regla que afecta a la pestana activa. El bloqueo manual y el desbloqueo forzoso se mantienen sin cambios.

## 2.3.15 - 2026-08-16

- Añade desbloqueo forzoso exclusivo desde el popup con confirmación explícita y persistencia por periodo restringido de Lima.
- Mantiene la pantalla de bloqueo de ShalomControl libre de pistas sobre la excepción manual.
- Conserva la prioridad del bloqueo manual y la sincronización inmediata entre popup y content script.

## 2.3.14 - 2026-08-16

- Refina la pantalla de bloqueo/desbloqueo de Service Order con un modal más claro, visualmente más limpio y alineado con el mockup de referencia.
- Añade bloques de advertencia e información para el desbloqueo fuera de horario sin alterar la lógica de horario ni la sincronización.
- Mantiene el alcance exclusivo para `https://sysnewos.shalomcontrol.com/service-order`.

## 2.3.13 - 2026-08-16

- Rediseña la pantalla de bloqueo de Service Order con una tarjeta clara y profesional, más alineada con la estética actual de ShalomControl.
- Suaviza el backdrop, mejora jerarquía, iconografía y énfasis del mensaje sin alterar la lógica de bloqueo ni la cuenta regresiva.
- Mantiene el alcance exclusivo para `https://sysnewos.shalomcontrol.com/service-order`.

## 2.3.12 - 2026-08-16

- Reconstruye el popup desde cero con un contenedor vertical único de 380 px y scroll interno principal para evitar la franja horizontal en Chromium.
- Aísla el CSS del popup y elimina las dependencias visuales problemáticas de iteraciones previas.
- Conserva la lógica funcional del token, la sincronización y el control de Service Order.

## 2.3.11 - 2026-08-16

- Reordena el popup para que la nueva identidad visual conserve el ancho fijo de 360 px y permita scroll vertical interno sin cortar la tarjeta de Service Order.
- Refina la jerarquía visual del popup con tarjetas más limpias, fondo con profundidad y acciones principales más legibles.
- Conserva intacta la lógica funcional del token, la sincronización y el bloqueo horario/manual.

## 2.3.10 - 2026-08-16

- Agrega bloqueo horario automático exclusivo para `https://sysnewos.shalomcontrol.com/service-order` entre 20:05 y 07:59:59 hora de Lima.
- Introduce bloqueo manual persistente en el popup con sincronización inmediata hacia la pestaña abierta.
- Mantiene el buscador existente sin cambios funcionales fuera de la nueva pantalla superpuesta de bloqueo.
- Suma pruebas de horario Lima, URL exacta, prioridad del bloqueo horario y estado manual.

## 2.3.5 - 2026-08-10

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

## 2.3.8 - 2026-08-13

### Changed

- Desplaza ligeramente hacia la derecha el panel de resultados para mejorar el encuadre visual en `service-order`.

## 2.3.7 - 2026-08-13

### Changed

- Sube la version de la extension para incluir la integracion neutral de `/service-order/` y su montaje robusto en SPA.

### Fixed

- Asegura que el buscador se inserte como componente unico antes del bloque de direccion en SYSNEWOS sin ejecutar `chosen`.

## 2.3.6 - 2026-08-13

### Added

- Compatibilidad con `https://sysnewos.shalomcontrol.com/service-order/` en modo neutral, sin selección automática ni efectos secundarios sobre la orden.

### Changed

- El buscador conserva el comportamiento existente en las rutas Shalom ya soportadas y separa explícitamente el modo neutral del interactivo.

### Fixed

- La inserción del buscador se reubica frente al bloque de dirección en `service-order` incluso durante re-renders de SPA.
- Se evita que `chosen` o cualquier clic sobre resultados modifique el formulario en la nueva pantalla neutral.

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
