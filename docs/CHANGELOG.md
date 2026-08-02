# Changelog

## 2026-07-30

- Se agrega la extension Chrome Buscador Shalom Control y el endpoint publico `/api/v1/extension/chrome/config`.
- Se amplia el contrato publico de agencias con campos seguros requeridos por clientes ligeros.


## 2026-07-28

- n8n ahora forma parte del compose principal como `codered-n8n`; se elimina la instalacion independiente en `/opt/n8n` y la imagen se construye localmente desde `docker/n8n/Dockerfile`.
- `n8n-nodes-codered` ahora usa exclusivamente codered-agent local mediante `CODERED_AGENT_LOCAL_URL` y `CODERED_AGENT_LOCAL_API_TOKEN` para Pair Instance y operaciones de ciclo de vida; el nodo ya no llama directamente a `/api/v1/integrations/n8n/pair` ni envía `instance_uuid` desde n8n.
- La credencial CodeRED de n8n ya no expone token local, Agent URL, Pair Code ni secretos; Pair Code es un parámetro temporal de operación.

Todas las versiones siguen `Keep a Changelog`.

## [Unreleased]

## [2.1.0] - 2026-08-02

### Added

- Fuente única de versión `2.1.0` para backend, API, comando Artisan, panel web, popup de la extensión, README y CHANGELOG.
- Endpoint público `GET /api/v1/version`, header `X-Application-Version`, `composer.json > extra.version` y comando `php artisan app:version` para consultar la versión activa del sistema.
- Roadmap versionado con `2.1.0` como versión actual y evolución planificada `2.1.0`, `2.2.0` y `3.0.0`.

### Changed

- El footer del panel administrativo muestra `CodeRED Platform v2.1.0`.
- La extensión Buscador Shalom se alinea con `manifest.json`, `package.json` y módulo compartido `EXTENSION_VERSION` en `2.1.0`.
- El popup de la extensión adopta diseño oscuro, ancho responsive de 520 a 720 px, layout de dos columnas, tamaños de texto optimizados y contenido ajustado para no mostrar barra de scroll en 1280x800.

### Removed

- El popup de la extensión deja de renderizar buscador, tarjetas de agencias, contador de resultados y acciones de Maps; esa experiencia queda reservada al content script inyectado en Shalom Control.


### Added

- Página pública `GET/POST /solicitar-token` para solicitudes de tokens sin login, con CSRF, throttling, honeypot, deduplicación y tracking `CR-XXXXXXXX` sin exponer tokens.
- El flujo público de solicitudes crea registros `pending` en `api_token_requests` sin requerir `agency_id`; el panel administrativo conserva aprobación, rechazo, cancelación y entrega segura.

### Changed

- El tipo AGENCIAS genera tokens con ability mínima `agencies:read` para la extensión Shalom Control.

### Added

- La aprobación y generación manual de tokens usan ahora `token_expires_in_days` (1-365, default 30) con preview en hora de Lima; `expires_in_minutes` queda como compatibilidad legacy para solicitudes n8n.
- Solicitudes de tokens simplificadas por tipo visible (`dni`, `ruc`, `agencies`) con mapeo centralizado a abilities Sanctum canonicas, aprobacion transaccional y compatibilidad con solicitudes n8n antiguas sin tipo.
- Flujo funcional de solicitudes de token para n8n: creación, consulta de estado, recuperación única del token aprobado, confirmación idempotente de entrega y cancelación segura a través de codered-agent firmado.
- `instance_uuid` estable para integraciones n8n, migración de idempotencia y comando `codered:n8n:deduplicate`.
- Guía `docs/integrations/n8n-lifecycle.md` para Pair, Challenge, Discovery, Heartbeat, rotación y recuperación.

- Especificación compartida `docs/integrations/protocol.md` con la máquina de estados `UNPAIRED → PAIRING → CHALLENGE → DISCOVERY → CONNECTED → DEGRADED → DISCONNECTED`.
- `ConnectionManager` en CodeRED Agent y en el nodo n8n para centralizar Pair, Challenge, Discovery, Heartbeat, Reconnect, rotación y estado.

- Configuración automática de PostgreSQL para n8n desde `Install_CodeRED-Platform.sh`.
- CodeRED Agent documentado como daemon persistente para Pairing, Discovery, Heartbeat y Capability Registry.
- Configuración segura del agente en `.env.example` con secretos vacíos y guía `openssl rand -hex 32`.
- Flujo interactivo del agente en `Install_CodeRED-Platform.sh`.
- Actualización por etapas en `update.sh` con backups, detección de cambios y healthcheck condicional del agente.
- Submenú administrativo de CodeRED Agent en `CodeRED.sh`.
- Comando Artisan `integrations:n8n-pair-code` para generar códigos temporales sin exponer secretos.
- Auditoría técnica en `docs/audits/technical-audit-2026-07-27.md`.

### Changed

- `Pair Instance` es ahora el asistente completo de conexión; las operaciones manuales de Discovery, Heartbeat y Challenge dejan de exponerse al usuario de n8n.
- Platform registra Challenge, Discovery y Heartbeat como etapas automáticas y confirma conexión solo con heartbeat reciente.

- Pair Instance, Test Connection y Reconnect del nodo n8n ahora se ejecutan exclusivamente a través de `codered-agent`; la credencial ya no guarda estado de pairing ni `shared_secret`.
- `codered-agent` expone `/api/v1/pair`, `/api/v1/status`, `/api/v1/test-connection` y `/api/v1/reconnect` protegidos por `localApiToken`, persiste el pairing cifrado en `/data` y devuelve respuestas saneadas.
- Platform debe considerar `connected` solo con heartbeat reciente; el pairing histórico sin confirmación del agente se trata como estado incompleto o degradado.

- El instalador puede crear o actualizar el rol/base `n8n`, aplicar privilegios y escribir el entorno de n8n sin exponer secretos.
- `docker-compose.yml` ahora toma URLs del agente desde `.env` en lugar de valores hardcodeados.
- `README.md` describe la arquitectura actual con CodeRED Agent, n8n y conectores futuros.
- La API local del agente valida challenge-response firmado desde CodeRED Platform.
- Discovery y heartbeat del agente ahora omiten estado unpaired y errores temporales sin cerrar Node.js.
- El nodo n8n en modo Agent consulta `/api/v1/status` como fuente de verdad para Test Connection.
- El almacenamiento cifrado del agente escribe `integration.enc` de forma atómica.

### Fixed

- Healthcheck de PostgreSQL alineado con `POSTGRES_DB`/`POSTGRES_USER`, sincronización segura de `POSTGRES_*` desde `DB_*` en instalación/actualización y espera explícita antes de migraciones o preparación de n8n.
- Seeders de roles y permisos idempotentes: se normalizan permisos, se deduplican IDs antes de `sync()` y se evita la violación de `permission_role_pkey` al reejecutar `db:seed --force`.
- Mensaje del instalador cuando falla `php artisan db:seed --force`, con indicación directa a `database/seeders` sin ocultar el error.
- Normalización de comillas externas en `DB_POSTGRESDB_PASSWORD` de n8n y escritura raw sin comillas añadidas.
- Pruebas shell para contraseñas n8n con `#`, `$`, `=`, espacios y comillas internas.
- Reinicio del agente cada ciclo de discovery cuando el estado local estaba unpaired.
- `integration.challenge` ahora usa `challenge_id`, expiración y respuesta firmada compatible con CodeRED Agent.
- Healthcheck del agente separado entre `/healthz`, `/readyz` y estado protegido `/api/v1/status`.

- Placeholder inseguro de secretos en `.env.example`.
- Riesgo de corrupción de `integration.enc` por escritura directa.
- Challenge del agente sin validación HMAC de entrada.
- Falta de herramientas operativas para healthcheck, pairing y rotación del token local del agente.
- API UI: nueva guía API basada en tarjetas, tester same-origin, autorización efímera y Swagger bajo demanda como referencia avanzada.
- API: sincronización incremental append-only con cursor HMAC, ETag/304, metadata de revisión, retención de cambios y Gzip en Nginx.
- Agencias: `zone` quedó fuera del flujo activo; la ubicación usa `department / province / district`, `place` añade el nombre de agencia, Shalom e importaciones priorizan `district`, y se añadió `agencies:repair-location-fields` para reparación manual auditada y no destructiva.

- API: Swagger UI renderiza el contrato OpenAPI con Authorize Sanctum, Try it out, duración y snippets; la copia de tokens usa Clipboard API con selección manual segura como fallback.
# Changelog

- API de agencias schema v2: agrega estado operativo legible y Centro de Operaciones booleano en listado, detalle, snapshot y sincronización incremental; metadata y OpenAPI anuncian la nueva capacidad.

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

- La aprobación y generación manual de tokens usan ahora `token_expires_in_days` (1-365, default 30) con preview en hora de Lima; `expires_in_minutes` queda como compatibilidad legacy para solicitudes n8n.
- Solicitudes de tokens simplificadas por tipo visible (`dni`, `ruc`, `agencies`) con mapeo centralizado a abilities Sanctum canonicas, aprobacion transaccional y compatibilidad con solicitudes n8n antiguas sin tipo.
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

- Reinicio del agente cada ciclo de discovery cuando el estado local estaba unpaired.
- `integration.challenge` ahora usa `challenge_id`, expiración y respuesta firmada compatible con CodeRED Agent.
- Healthcheck del agente separado entre `/healthz`, `/readyz` y estado protegido `/api/v1/status`.

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

## 2026-07-20 — API DNI y abilities separadas

- Se incorporó el módulo DNI con proveedor intercambiable, Redis, validación y respuesta controlada.
- Se separaron `agencias:consultar` y `dni:consultar`, con clientes API, límites y auditoría por servicio.
- Se añadieron rutas `/api/v1/agencias` y `/api/v1/dni/{dni}` sin retirar los contratos heredados.


## 2026-07-20 — Flujo DNI local-first y PeruDevs

- Se agrega `dni_records` como fuente principal con DNI string único.
- Se integra PeruDevs como respaldo configurable desde el panel y con token cifrado.
- Se separan cachés positiva/negativa, persistencia externa y errores 404/502/503 controlados.
- Se amplía la auditoría con origen, proveedor y hits local/caché.
- Se agregan permisos exclusivos de Super Administrador y pruebas con `Http::fake()`.


## 2026-07-20 — Contrato real PeruDevs y migración dni-api

- Se adapta PeruDevs a GET con `document` y `key`, sin Bearer.
- Se incorporan género y código de verificación; la fecha se normaliza y la edad es dinámica.
- Se añade caché negativa con hash, refresco asíncrono y `dni:import-legacy`.
- Se documenta la migración desde `dni_consultas` y la rotación hacia Sanctum.


## 2026-07-20 — Probador DNI y documentación API web

- Se agrega `/admin/api-tools/dni` con modos interno y endpoint efímero.
- Las ejecuciones administrativas usan `request_type=admin_test` y se excluyen de métricas de clientes.
- Se añaden rutas temáticas, visibilidad pública configurable, Swagger/OpenAPI y ejemplos Postman sin secretos.

## 2026-07-21 — Módulo RUC nativo

- Se migró la funcionalidad de consulta de `CodeRED-95/api-ruc` al dominio Laravel, con PostgreSQL y caché Redis.
- Se añadieron abilities independientes `ruc:consultar` y `ruc:buscar`, auditoría, rate limits y documentación OpenAPI.
- Se incorporó importación TXT por cola con streaming, progreso persistente, errores descargables y escritura idempotente.
- Se añadió el panel de registros/importaciones, probador administrativo RUC y métricas de dashboard.
- Se corrigió el portapapeles DNI con Clipboard API, fallback HTTP y notificación accesible.
# 2026-07-21

- El catálogo UBIGEO ahora se sincroniza manualmente desde Alanube mediante `ubigeos:sync`, conserva un snapshot offline y valida códigos de control antes del upsert.
- Se consolidó el lenguaje visual premium de CodeRED: shell con scroll independiente, sidebar persistente, tokens ampliados, tablas globales y primitivas accesibles para archivos, radios, alertas, cards y botones.
- Corregido el quoting seguro del instalador y el flujo de subida temporal del padrón RUC, con límites coherentes entre Nginx, PHP y Livewire.

- La importación masiva SUNAT quedó unificada en RUC: directorio privado, scanner, staging COPY, checkpoints, ubigeos precargados y worker `ruc-imports`.

## 2026-07-22 — Acciones reales para archivos RUC incoming

- Los botones Validar y Registrar invocan métodos Livewire públicos con carga y errores visibles.
- Se corrigió la expresión de sus acciones: los argumentos se construyen antes de pasarlos al componente Blade para que Livewire reciba JavaScript ejecutable, incluso con espacios o caracteres Unicode en la ruta.
- La validación inspecciona una muestra acotada, detecta encoding/delimitador y bloquea rutas inseguras.
- El registro evita duplicados por SHA-256 y deriva a cola el hash de archivos masivos.
- Se añadieron pruebas de formato, rutas hostiles, duplicados y autorización.

## 2026-07-29 - Rotación segura de tokens

- Agregado flujo `rotation` en `api_token_requests` junto al flujo histórico `issuance`.
- Agregadas referencias al token fuente y token de reemplazo, idempotencia por token y trazabilidad `revoked_by`/`revocation_reason`.
- La aprobación de rotaciones conserva exactamente propietario, tipo, scopes y `expires_at`; no reinicia vigencia.
- n8n y codered-agent incorporan la operación "Request Token Rotation" sin alterar pairing, discovery ni heartbeat.

## 2026-07-30 - Código personal Telegram y rotación por aprobación

- Agregado `public_code` UUID estable para usuarios y vinculación segura con Telegram tras aprobar solicitudes originadas desde n8n/Telegram.
- Agregados endpoints HMAC para consultar código personal y crear solicitudes de rotación por código personal sin exponer el token actual.
- Extendidos `codered-agent` y `n8n-nodes-codered` con operaciones `Get Personal Code` y `Request Token Rotation` basadas en Telegram.
- Agregado workflow JSON importable para `/codigo` y `/rotar CÓDIGO | MOTIVO`.
- La rotación mantiene activo el token anterior mientras está pendiente y reutiliza la aprobación transaccional existente para revocar solo después de generar el reemplazo.

- Seguridad: el panel de solicitudes de token ahora revela contactos completos solo bajo permiso explícito y acción auditada.
- Seguridad: al marcar una solicitud como entregada se eliminan los contactos cifrados completos y se conservan solo máscaras no reversibles.
- UI: el listado y detalle inicial de solicitudes de token ya no renderizan correo, Telegram ni WhatsApp completos.
