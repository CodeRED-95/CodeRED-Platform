- API UI: nueva guía API basada en tarjetas, tester same-origin, autorización efímera y Swagger bajo demanda como referencia avanzada.
- API: sincronización incremental append-only con cursor HMAC, ETag/304, metadata de revisión, retención de cambios y Gzip en Nginx.
- API: Swagger UI renderiza el contrato OpenAPI con Authorize Sanctum, Try it out, duración y snippets; la copia de tokens usa Clipboard API con selección manual segura como fallback.
# Changelog

- La documentación interactiva ahora descubre las abilities reales del Bearer Token, identifica acceso total, bloquea preventivamente endpoints sin permiso y muestra un resumen de disponibilidad sin depender de una ability fija.

- La guía API centraliza el Bearer Token en memoria para todas las tarjetas y conserva los estados HTTP reales sin presentarlos como errores de red.

- Endurecido el probador de documentación API: paths sin prefijo duplicado, parseo seguro, timeout por petición y separación real entre errores HTTP y errores de red.

- Corregido el limiter API para distinguir PersonalAccessToken, TransientToken y peticiones anónimas; el probador Bearer ahora omite cookies de sesión.

- Corregida la documentación API para usar rutas relativas, respetar HTTPS detrás de Cloudflare, normalizar Bearer Token y distinguir errores de red, autenticación, abilities y servidor.

## 2026-07-19 — API Sanctum y administración de tokens

- Se protegió la API oficial v1 con Sanctum, abilities, expiración, rate limit y CORS explícito.
- Se añadió un Resource mínimo de agencias, metadata, identidad del token y health público seguro.
- Super Administrador dispone de creación, visualización segura, rotación y revocación individual/masiva de tokens auditados.
- Se publicó OpenAPI 3 y documentación interactiva interna sin persistir credenciales.

## 2026-07-19 — Mapa, perfil y matriz de roles

- El mapa administrativo ahora usa Leaflet, tiles reales de OpenStreetMap, marcadores CodeRED, agrupación dinámica y ciclo de vida seguro con Livewire.
- El header quedó reducido al contexto de página y perfil, sin búsqueda global ni selector visible de tema.
- Se añadió Mi perfil para nombre, correo y contraseña de la cuenta autenticada, sin exponer campos administrativos.
- Los roles se redujeron de forma segura a Super Administrador, Consulta y Editor con una matriz exacta, rutas protegidas y redirección posterior al login por capacidad.

## 2026-07-19 — Paginación y operaciones masivas de papelera

- Se eliminó el salto al final al abrir listboxes teletransportados, limitando el desplazamiento a su panel y restaurando foco sin scroll.
- Se incorporó paginación oscura accesible y responsive con destino de scroll explícito por listado.
- La papelera de Agencias permite restaurar y eliminar definitivamente la selección visible, con autorización, transacciones, límite, confirmación reforzada y resumen.
- La eliminación permanente registra `force_deleted` en la auditoría global antes de borrar el registro y sus logs dependientes.

## 2026-07-19 — Importación desde Gist, fase 7

- Se convirtió el importador en un asistente de cinco pasos obligatorio.
- La validación analiza todas las filas y presenta válidos, advertencias, inválidos
  y duplicados antes de escribir en base de datos.
- Se centralizó la detección de duplicados para preview y Action.
- La importación utiliza un snapshot persistido del contenido validado y no vuelve a
  descargar la URL.
- Se añadió resumen final con importadas, actualizadas, omitidas, fallidas e
  incidencias persistidas.

## 2026-07-19 — Auditoría, fase 6

- Se añadió auditoría automática de Usuarios mediante observer y registrador seguro.
- Se normalizó la autoría `created_by` y `updated_by` en Usuarios y Agencias.
- Se registran responsable, fecha, IP, agente, valores y campos modificados.
- Se añadieron eventos explícitos para cambios de roles sin almacenar contraseñas,
  hashes ni tokens.
- Los historiales usan un componente común y solo se consultan con
  `users.view_activity` o `agencies.view_history`.

## 2026-07-19 — Papelera y soft delete, fase 5

- Se añadió soft delete aditivo al modelo Usuario.
- Usuarios y Agencias permiten filtrar activos, papelera o todos los registros.
- Se incorporaron acciones confirmadas y autorizadas para eliminar, restaurar y
  eliminar definitivamente.
- Se preservaron las protecciones de cuenta propia y último superadministrador.
- Se corrigió el observer de Agencias para respetar la integridad referencial en
  eliminaciones definitivas.

## 2026-07-19 — Dashboard profesional, fase 4

- Se trasladaron las consultas del dashboard desde Blade al componente Livewire.
- Se incorporaron métricas de usuarios y de todos los estados de agencias.
- Se añadieron una tendencia accesible de altas de siete días, distribución por
  estado, agencias recientes y resumen de la última importación.
- Las métricas administrativas se muestran únicamente cuando la cuenta dispone de
  los permisos correspondientes.

## 2026-07-19 — Experiencia de usuario, fase 3

- Se añadieron toasts globales, spinner accesible y skeletons con variantes.
- Se conectaron flashes de sesión y eventos Livewire al sistema de notificaciones.
- Se normalizaron estados de carga en formularios, importación y listados filtrables.
- Se añadió atrapado y restauración de foco en confirmaciones.

## 2026-07-19 — Design System, fase 2

- Se amplió `x-ui.input` con slots reutilizables de prefijo y sufijo.
- Se añadieron `x-ui.search-box` y `x-ui.confirm-dialog`, compatibles con Livewire y Alpine.
- Se migraron búsquedas, visibilidad de contraseña y confirmación de restablecimiento.
- Se documentaron contratos, responsabilidades y propagación de atributos HTML/Livewire.

## 2026-07-19 — Unificación visual, fase 1

- Se migraron formularios de agencias, importación, usuarios, login, layout y página
  404 a los componentes y tokens semánticos del CodeRED Design System.
- Se unificaron controles, validaciones, tarjetas, encabezados, acciones y estados de
  carga sin modificar contratos Livewire ni lógica de negocio.
- Se añadieron verificaciones contra estilos claros heredados y JavaScript inline.

## 2026-07-18 — Selector accesible de estados

- Se reemplazó el selector nativo de estado del formulario de agencias por un
  combobox Blade, Alpine y Livewire accesible, con panel oscuro, iconos y navegación
  completa por teclado.
- Se extendió el listbox personalizado a filtros, tamaño, fuente, estrategia, estado
  inicial y gestión de usuarios, eliminando `select` y `option` de todas las vistas.

Todas las versiones siguen `Keep a Changelog`.

## [Unreleased]

### Added

- Dashboard profesional con periodo real, ocho KPIs, gráficos SVG accesibles, actividad auditada y resumen completo de importación.
- Switches de usuario accesibles con etiquetas, ayuda y persistencia verificada de correo y cambio obligatorio de contraseña.

- Vista cartográfica integrada con Leaflet, tiles de OpenStreetMap, marcador CodeRED y ciclo de vida compatible con Livewire; jerarquía consistente para dropdowns, modales y toasts.

- Contratos accesibles y tokens base del CodeRED Design System, validados en la pantalla piloto de cambio de contraseña.

- Mapa administrativo de agencias con búsqueda, filtros, agrupación de coordenadas y enlaces seguros a Google Maps, sin dependencias cartográficas nuevas.

- Entorno reproducible con Dev Containers, configuración versionada de VS Code y verificadores `verify.sh`/`verify.ps1`.
- Script Composer `check` para ejecutar Pint, PHPStan y PHPUnit dentro del contenedor PHP.

- Módulo administrativo de usuarios con Livewire, Policy, reglas de seguridad y pantallas de detalle.
- Pantalla de cambio obligatorio de contraseña para cuentas marcadas por administración.
- Documentación específica para usuarios, estados y reglas críticas.
- Documentación modular del proyecto
- `AGENTS.md` como guía oficial para IA
- Carpeta `docs/adr` con decisiones arquitectónicas
- Módulo `Agencias Shalom` con panel administrativo, vista pública, detalle e importación
- Snapshot compacto para extensión y API pública de agencias
- Dashboard con estadísticas básicas del módulo
- CodeRED Design System con componentes Blade, tokens y página interna de referencia
- Login con traducciones en español y sincronización explícita de campos Livewire
- Login migrado a autenticación tradicional por sesión con `POST /login` para eliminar dependencia de Livewire en la pantalla de acceso
- Página `/admin/design-system` convertida en componente Livewire con layout administrativo
- Script de instalación reforzado con verificación del manifest actual de Vite

### Changed

- README principal convertido en portada
- Estructura documental centralizada en `docs/`

### Fixed

- PHPStan/Larastan nivel 5 estabilizado en cero errores sin baseline ni reglas de ignore.
- Errores reales corregidos en importación de Agencias, health de colas, filtro de usuarios, Resources, configuración cacheable y pruebas tautológicas.

- CRUD manual de Agencias estabilizado con normalización previa, procedencia protegida, validación de traslados, relaciones completas y cobertura de búsqueda/filtros.

- Login y sesiones reforzados con estado autoritativo, expulsión de cuentas bloqueadas y cambio obligatorio de contraseña protegido por middleware.
- Cobertura Feature ampliada para login, logout, CSRF, sesiones, recordatorio, roles y validaciones.

- Valores con espacios documentados con comillas en `.env`
- Referencia de puerto sincronizada con `8090`
- Reglas de permisos y usuario `www` documentadas
- Solución documental para `Class "Redis" not found`
- Explicación arquitectónica de PHP-FPM master root y workers `www`
- Solución permanente para Git Safe Directory en `/var/www/html`
- Corrección documental sobre la persistencia de `composer.lock`
- Flujo documentado para generar `public/build/manifest.json` con `npm run build`
- Corrección del prefijo API para evitar `api/api/v1`
- Estrategia documentada para usar `DB_*` como fuente de PostgreSQL
- Explicación de cómo sincronizar credenciales de PostgreSQL cuando existe un volumen inicializado
- Inclusión del comando `health:redis` para verificar Redis sin Tinker
- Corrección técnica de la migración `000009` para eliminar la restricción UNIQUE como constraint y crear un índice único parcial
- Flujo frontend documentado para generar `package-lock.json` con `npm install` en el primer inicio y usar `npm ci` en instalaciones posteriores
- Redis configurado sin `AUTH` cuando el servidor no utiliza contraseña
- Eliminada la duplicación de Alpine al dejar que Livewire 3 cargue la única instancia activa
- Estrategia de autorización reorientada a Gates y Policies sin sobrescribir `User::can()`
- Corrección del acceso al módulo Agencies mediante `Gate::before` con bypass de `super-admin`
- Mapeo de abilities del módulo Agencies a permisos reales para que `viewAny`, `create`, `update` e importación respeten Policies y accesos operativos
- Roles, permisos y asignación del administrador reorganizados con `RolesAndPermissionsSeeder`
- Factories modulares explicadas con `newFactory()` y seeders separados por responsabilidad
- Bootstrap automático del contenedor aplicado al arranque para evitar pasos manuales de Artisan
- Rediseño del layout administrativo, login, dashboard y vistas clave con el CodeRED Design System

### Removed

- Ninguno

- Agencies: se añadió `external_id` sin reemplazar la PK, se separaron los textos Chosen terrestre/aéreo y se mantuvo compatibilidad temporal con `texto_chosen`.
- Importador/API: nuevo formato de identificadores, clasificación segura del formato heredado y detección de conflictos entre ID externo, Code y referencia.

- Agencies: selección por fila y página visible, activación masiva de registros En revisión y eliminación masiva mediante Soft Delete.
- API: contrato español de agencia con `internal_id`, `id`, Code, ubicación, tamaño e identificadores terrestre/aéreo, preservando aliases anteriores.

- UI: dropdowns, selects y confirmaciones usan un portal global con posicionamiento adaptativo; la escala de capas y la región única de toasts quedan centralizadas.

- Dashboard: rediseño ejecutivo compacto con cuatro KPIs, métricas secundarias, tendencia SVG segura, donut real, actividad limitada y última importación resumida.
