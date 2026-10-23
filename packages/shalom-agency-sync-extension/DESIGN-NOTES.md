# Notas de diseño del sincronizador de agencias Shalom

Este documento permite a quienes mantienen la extensión contrastar su interfaz y sus decisiones con el sistema administrativo existente de CodeRED Platform. Documenta la implementación local mediante lectura de fuentes; no constituye una revisión visual, una prueba funcional ni evidencia de producción. El alcance corresponde exclusivamente a `packages/shalom-agency-sync-extension`.

## Dirección y autoridad

La superficie corresponde al modo **Operate**: una cuenta administradora captura destinos terrestres, revisa diferencias y aplica cambios de estado con consecuencias globales en Platform. Es una extensión ordinaria del sistema existente, separada del buscador y de Shalom Recordar; no establece otra identidad.

Las autoridades consultadas son [PRODUCT.md](../../PRODUCT.md), [DESIGN.md](../../DESIGN.md), [.impeccable/design.json](../../.impeccable/design.json), [el sistema de diseño documentado](../../docs/DESIGN_SYSTEM.md) y [resources/css/app.css](../../resources/css/app.css). Se aplica la referencia `C:\Users\coder\.agents\skills\impeccable\reference\document.md` como método de extracción y contraste, con el límite explícito de escribir solo estas notas. No se regeneran `DESIGN.md` ni su sidecar, no se repara deriva del sistema existente y no se convierte este flujo en gramática general de Platform.

La evidencia funcional procede de `src/dashboard.html`, `src/dashboard.css`, `src/dashboard.js`, `src/content.js`, `src/capture.mjs` y `src/background.js`, contrastados con el backend y los dos documentos del flujo. La existencia de estos archivos prevalece sobre el indicador `hasVisualImplementation: false` devuelto por el cargador de contexto. No se modifica ese diagnóstico.

## Continuidad con el sistema existente

La comparación es de fuentes y contratos. La interfaz conserva tinta, papel mineral y bermellón, el símbolo oficial y la pareja Inter / Space Grotesk. El paquete transpone componentes a HTML y CSS propios del contexto de extensión; no ejecuta Blade ni importa sus contratos `wire:*`.

Las correspondencias y diferencias observadas son las siguientes:

| Aspecto | Sistema existente | Implementación del paquete y decisión documentada |
| --- | --- | --- |
| Fondo y superficies | `--color-background`, `--color-background-elevated`, `--color-surface`, `--color-surface-hover` | `--background` (`#141413`), `--elevated` (`#1a1a17`), `--surface` (`#23231f`) y `--hover` (`#30302a`) conservan los valores de origen. |
| Texto y bordes | Texto primario, secundario y tenue; borde normal y sutil | `--text`, `--secondary`, `--muted`, `--border` y `--subtle` reproducen respectivamente `#f7f4ea`, `#c9c4b5`, `#9b9687`, `#5a584e` y `#39382f`. El papel mineral aparece como texto sobre tinta. |
| Acción | `--color-brand: #d53629`; hover `#e74738` | `--brand` conserva el bermellón. La última declaración de `--brand-hover` sustituye el valor inicial por `#c83125`: es una diferencia local, no una actualización del token global. |
| Tipografía | Inter para interfaz, Space Grotesk para títulos; JetBrains Mono para datos técnicos | Se empaquetan Inter y Space Grotesk localmente. El cuerpo usa `14px/1.6`; los títulos usan Space Grotesk. No se empaqueta JetBrains Mono y el recibo técnico conserva Inter: adaptación observada, no equivalencia completa. |
| Marca | Emblemas oni existentes | `scripts/build.mjs` copia `public/images/branding/codered-symbol.png` a `assets/icon.png`; el encabezado y favicon lo reutilizan. No hay un símbolo nuevo para el sincronizador. |
| Formas y densidad | Radio de control de `8px`, tarjeta de `16px`; altura semántica de control de `3rem` | Botones/campos con radio de `8px`, paneles de `16px` y relleno principal de `24px`. Los botones tienen mínimo de `40px` y los campos de `42px`, distintos del control de referencia de `48px`. |
| Capas | Bordes semánticos y elevación para capas que la necesitan | Paneles planos sobre fondo elevado, divisores y tabla delimitada; no se copia el blur ni las sombras de `glass-panel`. No se prescribe eliminar esos recursos del sistema existente. |
| Estados | Semántica de éxito, advertencia y error | `--success: #79e7a0`, `--warning: #f9ce7a` y `--danger: #ff968d`, con fondos y bordes locales de avisos. Mantienen los roles, pero no son copias literales de los colores globales. |
| Foco e iconografía | Foco visible e iconos SVG en `x-ui.*` | El CSS incluye contorno de `2px` con separación de `3px` y color local `#ff998e`. El enlace «Abrir Shalom ↗» usa un glifo de texto: diferencia frente a la convención SVG, sin corregirla aquí. |

Evidencia: [tokens y tipografía de Platform](../../resources/css/app.css), líneas 10–76; [CSS del dashboard](src/dashboard.css), líneas 1–8; [botón](../../resources/views/components/ui/button.blade.php), [campo](../../resources/views/components/ui/input.blade.php), [tarjeta](../../resources/views/components/ui/card.blade.php) y [aviso](../../resources/views/components/ui/alert.blade.php) existentes; [copia de marca y fuentes](scripts/build.mjs), líneas 12–16. El texto blanco del botón primario también existe en el componente Blade; no se presenta como una innovación del paquete ni se cambia la descripción mineral de `DESIGN.md`.

## Composición y lectura operativa

El encabezado identifica CodeRED, el área Agencias y la versión independiente. La cuenta y el consentimiento de automatización ocupan la columna lateral; captura, comparación y recibo ocupan el área de trabajo. Esta composición pertenece a este sincronizador y no define una plantilla obligatoria para otros módulos.

El contenedor llega a `1400px`, con columna lateral de `300px` y separación de `24px`. A `1000px` la columna lateral pasa a `260px` y las acciones se reorganizan; a `720px` la composición pasa a una columna y el botón de aplicación ocupa todo el ancho. Son breakpoints locales; no sustituyen los `640px` y `1024px` del sidecar global. La tabla conserva desplazamiento propio, cabecera sticky y altura máxima de `440px`.

La comparación muestra conteos, nombres con ubicación, estado anterior, cambio propuesto, origen protegido y vencimiento en hora de Perú. Un bloque desplegable conserva los motivos de revisión manual. Los textos «Activar» y «Desactivar» acompañan al color; no se exige interpretar únicamente verde o ámbar. El estado vacío explica que hacen falta captura y sesión.

El HTML contiene etiquetas de campos, encabezados asociados a secciones, caption de tabla, `scope="col"`, avisos de estado y una región viva para el recibo. El CSS incluye foco visible y una regla de movimiento reducido. Esto acredita recursos presentes en las fuentes, sin certificar contraste, navegación con teclado ni comportamiento responsive en ejecución.

Evidencia: [estructura del dashboard](src/dashboard.html), líneas 10–48; [renderizado](src/dashboard.js), líneas 30–80; [composición y media queries](src/dashboard.css), líneas 4–7.

## Captura, firma DOM y alcance del catálogo

La captura reconoce una **firma estructural del DOM**: ruta de PRODUCTOS, transporte terrestre marcado, encabezado «Seleccionar agencia de destino», placeholder del buscador, `ul[role="listbox"]`, opciones con `data-index` y rótulos con ubicación y agencia. Esta firma es un reconocimiento de estructura, no una firma criptográfica de Shalom.

Las protecciones observadas son las siguientes:

1. `readSnapshot()` exige la URL prevista, transporte terrestre, buscador exactamente vacío, lista visible y final del desplazamiento observado con tolerancia de dos píxeles. Rechaza indicadores `[aria-busy="true"]` o `[role="progressbar"]` dentro del modal.
2. Exige al menos 100 opciones, índices consecutivos desde cero y formato de ubicación/nombre reconocido. Obtiene el origen del encabezado; si no lo identifica, interrumpe la captura.
3. `stableCapture()` desplaza solo la lista hasta el final, espera un segundo y compara la huella de origen y entradas. Acepta dos lecturas consecutivas iguales, con un máximo de seis intentos, y restaura `scrollTop` en `finally`. Cada lectura vuelve a comprobar el final y la ausencia de los indicadores de carga reconocidos.
4. El background comprueba procedencia, canal, filtro, marcas de final/completitud, dos lecturas estables, entre 100 y 3000 entradas y coherencia entre `total` y longitud. El backend valida fechas, formato, cantidad e índices antes de comparar.

La ausencia de esos indicadores significa «sin carga reconocida por este DOM»; no demuestra que Shalom no tenga otra carga pendiente. `total` se calcula como `entries.length`: **el total global externo es desconocido**. Tampoco existe un manifiesto firmado ni una prueba de que otros orígenes o cuentas reciban los mismos destinos. `complete: true`, la huella local y `snapshot_hash` describen o identifican la captura; no autentican un catálogo global de Shalom.

Por ello, el mínimo de opciones, las lecturas estables y los límites porcentuales son barreras operativas. El estado modificado en Platform es global, mientras que la lista observada depende de sesión y origen. El README y la guía técnica coinciden en exigir revisar el primer resultado y evitar automatización global cuando Shalom restringe la lista de esa cuenta.

Evidencia: [captura estructural](src/capture.mjs), líneas 4–38; [estabilización](src/content.js), líneas 8–25; [validación del background](src/background.js), líneas 90–102; [solicitud del backend](../../app/Http/Requests/Api/V1/NativeAgencySnapshotRequest.php), líneas 16–28; [README](README.md), líneas 58–79; [límite documentado del DOM](../../docs/SHALOM_NATIVE_AGENCY_SYNC.md), líneas 101–105.

## Comparación y protecciones de negocio

Platform decide las coincidencias y los cambios, no el índice de la lista nativa. Compara ubicación completa y nombre normalizados; admite un nombre anterior cuando la coincidencia resulta única. Activa las presentes identificadas, con excepción del origen protegido y de las trasladadas. Solo desactiva ausentes activas con referencia terrestre y sin protección. Las desconocidas, ambiguas, trasladadas y ausentes sin referencia terrestre se destinan a revisión sin crear registros ni aplicarles esos cambios.

Un origen no único, duplicados, dos destinos que apuntan a una misma agencia, coincidencia inferior al 80 % o desactivación superior al 10 % del catálogo terrestre activo bloquean el plan. El denominador de ese último límite son las agencias activas con `texto_chosen_terrestre`, no todas las entradas capturadas ni todo el catálogo de Platform. Los límites configurables observados son mínimo de 100 entradas, ratio máximo de `0.10` y plan de 10 minutos.

La captura debe tener menos de 10 minutos al comparar; el plan recibe su propio vencimiento de 10 minutos. Aplicar envía únicamente `preview_id`, ligado a cuenta y sesión. El servidor vuelve a autorizar, comprueba la revisión del catálogo y aplica en transacción con auditoría. El recibo permite identificar la operación; su consulta y reutilización dependen de su vigencia en caché.

Evidencia: [comparación](../../app/Modules/Agencies/Services/NativeAgencyComparison.php), líneas 26–112; [controlador](../../app/Http/Controllers/Api/V1/Admin/NativeAgencySyncController.php), líneas 37–115; [configuración](../../config/agency_native_sync.php); [reglas documentadas](../../docs/SHALOM_NATIVE_AGENCY_SYNC.md), líneas 54–91. Se describe el código leído, sin afirmar ejecución o aplicación real.

## Aplicación manual y autoswitch

El control de automatización es un checkbox de activación manual y consentimiento explícito. Arranca apagado y se presenta deshabilitado hasta disponer de cuenta administrativa. Activarlo pide confirmación de que podrá activar y desactivar agencias en Platform; cancelar restaura el estado representado. Apagarlo es una operación local que no depende de la red.

Con el modo apagado, una captura no aplica cambios: la comparación y la aplicación se solicitan desde sus botones, y la aplicación confirma conteos y origen. Con el modo encendido, una captura válida puede comparar y aplicar sin otra confirmación. Para hacer visible esa consecuencia, el botón cambia a **Capturar y aplicar automáticamente** y aparece una advertencia en la sección de captura. No hay un cambio silencioso del modo manual al automático.

El observador del content script puede registrar una captura válida aunque el modo automático esté apagado; el background solo ejecuta comparación y aplicación automáticas si `auto`, la autenticación y la ausencia de operación pendiente lo permiten. La huella evita repetir la última captura pasiva aceptada; la captura manual permite repetirla. No hay un rastreador periódico ni se genera una orden, agrega un producto o elige un destino. Si Shalom lo requiere, la persona habilita el destino mediante su borrador.

Cambiar de cuenta elimina plan y consentimiento. Cerrar sesión apaga primero la automatización; si falla la revocación remota, conserva los tokens de sesión para reintentar e informa el resultado. Un resultado incierto al aplicar también apaga el modo automático y exige comprobar la operación pendiente.

Evidencia: [controles y confirmaciones](src/dashboard.js), líneas 35–40 y 90–111; [captura manual y pasiva](src/content.js), líneas 28–61; [automatización, cuenta y cierre](src/background.js), líneas 90–170; [README](README.md), líneas 44–56 y 94–103.

## Vistas de éxito, bloqueo y resultado incierto

Las vistas responden a estados diferentes de la operación:

| Vista | Evidencia en la implementación | Significado operativo |
| --- | --- | --- |
| Éxito | `#receipt`, `.notice.success`, texto «Sincronización completada» y renderizado de `state.receipt` | Aviso verde persistente mientras el recibo permanezca en el estado de sesión; muestra activadas, desactivadas, pendientes, origen, fecha de Perú, administrador y operación. No tiene cierre temporizado. La aplicación manual y la consulta de resultado llevan el foco al recibo. |
| Bloqueo | `#blockers`, `.notice.warning`, `blocked_reasons` y `plan.can_apply` | Expone motivos del servidor junto al plan y deshabilita aplicar. La tabla representa propuestas, no cambios ya efectuados. El botón también se deshabilita por falta de sesión, operación pendiente, ausencia de cambios o vencimiento al renderizar. |
| Resultado incierto | `#pending`, texto «Resultado pendiente de comprobar», `state.pending` y automatización apagada | Un fallo de red o respuesta de servidor de 500 o superior no demuestra que no hubo cambios. **Comprobar resultado** consulta el recibo sin reaplicar. El descarte manual exige confirmar que se revisaron estados y auditoría; no revierte la operación. |

Un recibo anterior no se borra al capturar o comparar de nuevo. Si aparece junto a un plan posterior, su fecha y UUID identifican la operación confirmada anterior: no acredita que ese nuevo plan se aplicó. La vista de éxito de un fixture tampoco acredita una aplicación real.

Evidencia: [regiones de estado](src/dashboard.html), líneas 13–14 y 39–48; [renderizado y foco](src/dashboard.js), líneas 53–79 y 99–106; [aplicación y recuperación](src/background.js), líneas 71–85 y 145–154.

## Credenciales y sesión administrativa

La extensión utiliza la API oficial de autenticación con `application=platform` y un nombre de dispositivo propio. No reutiliza como autorización el hecho de tener abierta la sesión web de Platform ni una sesión de Shalom. El backend exige una sesión de Platform, rol `admin` o `super-admin` y permisos de consulta y cambio de estado.

La contraseña existe transitoriamente en el envío de login; el dashboard limpia el campo al enviarlo y el background no la persiste. Los tokens de acceso y renovación se conservan en **`chrome.storage.session`**, con `TRUSTED_CONTEXTS`; no se guardan en `storage.local`, sincronización de Chrome ni archivos. El mensaje de estado omite `auth`, y el content script no recibe tokens. Cerrar completamente Chrome elimina ese almacenamiento local de sesión; no equivale por sí solo a revocar la sesión remota.

El manifiesto solicita `storage` y acceso a los dominios de Platform y Shalom. Las solicitudes de autenticación y sincronización se realizan desde el background hacia Platform, con cookies omitidas. Los datos de negocio capturados son nombres/ubicaciones de destinos, origen y fecha; no se extraen productos, pagos, clientes ni órdenes.

Evidencia: [envío y limpieza del campo](src/dashboard.js), líneas 90–94; [almacenamiento, HTTP y login](src/background.js), líneas 1–20, 107–133 y 186; [manifiesto](manifest.json); [autorización del servidor](../../app/Http/Controllers/Api/V1/Admin/NativeAgencySyncController.php), líneas 119–129; [privacidad documentada](README.md), líneas 81–103.

## Fixtures visuales y límite de evidencia

Los cuatro archivos existentes bajo `.impeccable/review/` son fixtures visuales explícitos de este paquete. Se registran por el alcance indicado y por la lectura del simulador, sin abrir el navegador, regenerar capturas ni ejecutar una revisión visual:

| Archivo relativo al paquete | Uso documental | Lo que no acredita |
| --- | --- | --- |
| `.impeccable/review/desktop.jpg` | Referencia de composición de escritorio. | Una captura autenticada de producción o corrección visual verificada en esta tarea. |
| `.impeccable/review/mobile.jpg` | Referencia de composición estrecha. | Validación responsive o de interacción táctil ejecutada aquí. |
| `.impeccable/review/blocked.jpg` | Referencia del estado de bloqueo simulado. | Que un catálogo real superó el umbral de desactivación. |
| `.impeccable/review/success.jpg` | Referencia del recibo de éxito simulado. | Una sincronización real, auditoría persistida o agencia modificada. |

`tests/visual-preview.mjs` sirve `dist` en el puerto 4176, inyecta un `chrome` simulado, fabrica usuario, plan y recibo, y añade «PRUEBA VISUAL — DATOS SIMULADOS». Su CSP incluye `connect-src 'none'`; los modos `blocked`, `success` y `auto` son estados artificiales, no ejecuciones del comparador. `scripts/preview.mjs`, puerto 4175, es otra vista estática: fuera del contexto de extensión, el dashboard informa que no puede iniciar sesión ni sincronizar. Ambos scripts se leyeron, no se ejecutaron.

En la inspección local, los SHA-256 de `src/dashboard.css` y `dist/dashboard.css` coinciden; también coinciden los de `src/dashboard.js` y `dist/dashboard.js`. Esto relaciona esas dos fuentes con sus copias locales de distribución, sin vincular criptográficamente los JPG a una versión ni verificar todo el bundle. Los fixtures están ignorados por `.gitignore`; su presencia local no garantiza que acompañen un checkout o una publicación.

Evidencia: [simulador visual](tests/visual-preview.mjs), [preview estática](scripts/preview.mjs), [modo sin extensión](src/dashboard.js), líneas 113–117, y [.gitignore](.gitignore).

## Coherencia documental y contradicciones registradas

El [README del paquete](README.md) y [SHALOM_NATIVE_AGENCY_SYNC.md](../../docs/SHALOM_NATIVE_AGENCY_SYNC.md) coinciden en separación del cliente, autenticación oficial, comparación sin mutaciones, origen protegido, revisión de excepciones, límites porcentuales, aplicación por `preview_id`, resultado incierto y limitación de completitud. No se identifica una contradicción directa entre ambos en esos contratos. El ejemplo abreviado de la API declara expresamente que no es una captura válida; su `total: 1` no contradice el mínimo de 100.

Se registran estas tensiones y precisiones sin editar sus fuentes:

| Ubicación | Contraste con las fuentes | Decisión para interpretar el trabajo |
| --- | --- | --- |
| `src/dashboard.html`, líneas 12 y 29 | «La lista terrestre nativa define qué agencias deben estar activas» y «Activa las presentes y desactiva las ausentes» omiten excepciones que sí detallan el README, líneas 39–42 y 68–79, y la guía API, líneas 83–105. | Son resúmenes incompletos. No autorizan activar el origen o trasladadas, desactivar ausentes sin referencia terrestre ni tratar la lista de una sesión como catálogo global certificado. |
| `src/dashboard.html`, línea 51 | El pie menciona «más del 10 % de desactivaciones» sin explicitar denominador. Los documentos y `NativeAgencyComparison.php`, líneas 91–94, sí lo restringen al catálogo terrestre activo. | El límite no se calcula sobre `native_count` ni sobre todas las agencias. |
| `README.md`, líneas 72–73 | «Reintentar la misma aplicación devuelve su recibo» omite la condición que sí recoge la guía API, líneas 69–70: vigencia en caché. El controlador devuelve 409 si ya no existe la operación. | No se interpreta como idempotencia ni recuperación indefinidas. Un recibo no disponible requiere comprobación de estados y auditoría. |
| `README.md`, líneas 53–54 | «Una lista sin cambios se captura una sola vez por carga» resume la deduplicación. `content.js`, líneas 3 y 54–57, solo recuerda la última huella pasiva aceptada; una secuencia A → B → A puede volver a registrar A. | Es supresión de repeticiones consecutivas, además de la repetición manual explícita. |
| `README.md`, línea 56 | La frase sobre permisos admite que la comparación siga visible. `background.js`, línea 56, apaga la automatización y borra el plan cuando procesa un 403. | Puede existir una vista previa anterior antes de revalidar; tras ese 403, el código invalida el plan local. La autorización efectiva permanece en el servidor. |
| `DESIGN.md`, sidecar y CSS local | Hover, foco, tonos de estado, altura de controles, tipografía técnica y glifo de enlace no coinciden literalmente en todas las fuentes. | Se conservan como diferencias observadas en esta extensión. Estas notas no aprueban una nueva identidad, no actualizan tokens generales y no reparan deriva incumbente. |

## Alcance de la comprobación

Se realiza lectura estática del producto, diseño, componentes existentes, cliente, backend y documentación; inventario de los fixtures y comparación de hashes de las dos parejas CSS/JS indicadas. No se ejecutan pruebas, build, empaquetado, detectores de UI, preview ni navegador. No se comprueban login, captura de Shalom, sincronización, auditoría o producción en ejecución.

La única escritura de esta tarea es este `DESIGN-NOTES.md`. Se preservan `PRODUCT.md`, `DESIGN.md`, `.impeccable/design.json`, ambos documentos contrastados y todos los archivos de implementación. La revisión visual y la validación funcional corresponden a tareas separadas; ningún estado simulado se presenta como resultado productivo.

## Cierre comunicado

La actualización de cierre recibida del usuario informa estos resultados de las tareas de revisión y validación:

- `finish-reviewer`: siete de siete hallazgos resueltos, con disposición `ship` limitada al alcance de esos siete hallazgos.
- Node: 25 pruebas aprobadas.
- Backend: ocho pruebas aprobadas, con 55 aserciones.
- Autenticación: 18 pruebas aprobadas, con 63 aserciones.

Estos resultados se registran como evidencia comunicada por el usuario; no se ejecutan ni verifican de nuevo sus suites o informes en esta tarea documental. La disposición `ship` no amplía el alcance revisado ni convierte los fixtures en evidencia productiva. No se prevén más cambios de UI para este cierre.

La autorización de publicación comunicada se limita a la **API**. No autoriza aplicar cambios de estado sobre agencias en producción ni publicar otras extensiones. Esta anotación registra la autorización, no acredita un despliegue realizado. Las precisiones documentales de la sección de coherencia permanecen registradas sin modificar el README, la guía técnica ni el sistema de diseño existente.
