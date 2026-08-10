# Registro de Cambios

Todos los cambios notables en CodeRED Platform se documentan en este archivo.

El formato se basa en [Mantener un Changelog](https://keepachangelog.com/es-ES/1.0.0/) y este proyecto sigue [Versionado Semántico](https://semver.org/lang/es/).

---

## [4.0.5] - 2026-08-10

### ℹ️ Nota

- Bump manual

---

## [4.0.4] - 2026-08-10

### ℹ️ Nota

- Bump manual

---

## [4.0.3] - 2026-08-10

### ℹ️ Nota

- Bump manual

---

## [4.0.2] - 2026-08-10

### ℹ️ Nota

- Bump manual

---

## [4.0.1] - 2026-08-09

### ℹ️ Nota

- Bump manual

---

## [4.0.0] - 2026-08-10

### CAMBIOS INCOMPATIBLES

El **importador manual de agencias fue retirado**. La gestión del padrón de
agencias se hace ahora con **copias de seguridad y restauración**, igual que ya
ocurría con el padrón RUC desde 3.0.0.

- Rutas eliminadas: `GET /admin/agencies/import` y
  `POST /admin/agencies/import/preview`. La primera devuelve ahora 404.
- Tablas eliminadas: `agency_imports` y `agency_import_failures`
  (migración `2026_08_10_000003_drop_agency_import_tables`).
- Clases eliminadas: `ImportAgenciesAction`, `AgencyImportPreviewService`,
  `AgencyImportPreviewController`, `PreviewAgencyImportRequest`,
  `AgencyImportNormalizer`, `AgencyImportPayloadReader`, `AgencyImportRowData`,
  los modelos `AgencyImport` y `AgencyImportFailure`, los enums
  `AgencyImportStatus` y `AgencyImportStrategy` y el componente Livewire
  `Admin\Agencies\Import`.
- El panel muestra ahora **“Última sincronización Shalom”** donde antes
  mostraba la última importación manual.

**La sincronización Shalom NO se toca.** `agency_import_runs`,
`agency_import_items`, `SyncShalomAgenciesJob`, `ConfirmAgencyImportRunAction`,
`ChosenFileParser`, las rutas `/admin/agencies/import/shalom` y
`/admin/agencies/import/run/*` y el permiso `agencies.import` siguen igual: los
usa la sincronización, no el importador retirado.

### AÑADIDO

Sistema de **Backup y Restauración de agencias**.

- **El respaldo captura la fila completa.** Antes descartaba `created_by`,
  `updated_by` y `zone`, así que no permitía una restauración fiel. Ahora vuelca
  todas las columnas de `agencies` (leídas del esquema, de modo que una columna
  nueva entra sola) más `agency_name_histories`. Formato `schema_version: 2`;
  la restauración sigue aceptando archivos v1.
- **Restauración desde archivo subido** (`.json`, hasta 200 MB) o desde una
  copia ya registrada, con dos modos:
  - *Combinar* (por defecto): crea y actualiza, no elimina nada.
  - *Réplica exacta*: además envía a la papelera lo que no esté en la copia.
    **Nunca borra de forma definitiva**, así que siempre es reversible.
- **Se ejecuta en cola** (`RestoreAgencyBackupJob`) con progreso por etapas en
  la propia pantalla. Ninguna petición HTTP espera al proceso, de modo que una
  copia grande no puede agotar el tiempo de Cloudflare.
- **Copia de seguridad automática antes de escribir nada**, enlazada desde la
  restauración para poder deshacerla.
- **Emparejamiento por `code`, no por id**: al restaurar sobre una base donde
  las agencias se recrearon, los ids no coinciden pero el código sí. Los ids del
  archivo se traducen con un mapa que después resuelve `moved_to_agency_id` y el
  historial de nombres, de modo que una agencia trasladada conserva su destino.
- **El feed incremental sigue coherente**: la restauración escribe por consulta
  directa para no disparar la regeneración de `slug`, `map_url` y `place`, pero
  alimenta `agency_sync_changes` explícitamente al terminar.
- Nueva tabla `agency_backup_restores` y permiso `agencies.backup.restore`.

### CORREGIDO

- **Filtros de tres estados en el listado.** *Chosen Terrestre*, *Chosen Aéreo*
  y *Cambió de nombre* solo ofrecían “Todos” y “Sí”; ahora ofrecen también
  “No”. La comprobación anterior usaba `empty()`, con lo que un `'0'` se
  descartaba como si no hubiera filtro y “No” nunca habría funcionado. Además
  “Sí” exige valor no vacío y “No” acepta tanto `NULL` como cadena vacía.
- **Se retiró la búsqueda por Clasificación** del listado y de sus filtros.
- **Un segmento no numérico ya no provoca un error 500.**
  `/admin/agencies/import` caía en la ruta `/admin/agencies/{agency}` y llegaba
  a PostgreSQL como id, que rechazaba el texto. Las rutas de detalle, edición y
  traslado se restringen ahora con `whereNumber`.

---

## [3.5.0] - 2026-08-10

### CAMBIADO

Sistema de versionado con **fuente única de verdad**. La versión estaba
duplicada en cinco sitios (`.env`, `.env.example`, `config/version.php`,
`config/app.php` y `composer.json`) y la copia del `.env` ganaba sobre el
código: una instalación con `APP_VERSION=3.2.0` heredado reportaba esa versión
en el footer, en `GET /api/v1/version` y en los metadatos de backup aunque el
código desplegado fuese 3.4.0.

- **`composer.json > extra.version` es ahora la única definición.**
  `App\Support\Version` la lee (con caché por proceso) y de ahí derivan
  `config/version.php`, `config/app.php`, la UI, la API, los comandos y los
  scripts de despliegue.
- **`APP_VERSION` ya no se consulta** y se eliminó de `.env` y `.env.example`.
  `.env.example` no la reintroduce a propósito: sería una segunda fuente de
  verdad. Un `.env` heredado que aún la defina se ignora, y `./update.sh`
  elimina la línea automáticamente (`migrate_legacy_app_version`).
- **`config/version.php` expone además `version.source`**, la ruta del archivo
  que define la versión.

### AÑADIDO

- **`bin/version.sh`** — consulta la versión desde el host sin levantar Laravel
  ni entrar en un contenedor. No depende de PHP ni de `jq`: los usa si están
  disponibles y si no extrae el valor con `sed`/`grep`. `--source` imprime la
  ruta de la fuente de verdad.
- **`app:bump-version --dry-run`** para ver la versión resultante sin escribir.
- **`App\Support\Version`** con validación SemVer estricta
  (`MAJOR.MINOR.PATCH`, con prerelease/build opcionales) y cálculo de bump.
  Una versión malformada se rechaza en lugar de propagarse.
- **`update.sh` informa del salto de versión** (`Versión: 3.4.0 -> 3.5.0`) y,
  tras reconstruir la caché, verifica que la app dentro del contenedor reporta
  exactamente la misma versión que `composer.json`; si no coinciden, avisa con
  el comando de corrección. Es el fallo clásico de contenedor sin recrear o
  `config:cache` obsoleta.
- Cobertura de versionado: `tests/Unit/VersionTest.php` (SemVer, bump,
  validación) y `SystemVersionTest` ampliado, que ahora compara contra
  `composer.json` en vez de contra una constante y comprueba que un
  `APP_VERSION` heredado no altera la versión reportada.

### CORREGIDO

- **`app:bump-version` ya no reescribe archivos de configuración.** Modificaba
  `config/version.php` y `config/app.php` por expresión regular y los corrompía:
  `"$1"` seguido del número se interpretaba como la retrorreferencia `$13`, y
  dejaba `.4.0'),` en lugar de la versión. Ahora escribe un único archivo.
- **La entrada del CHANGELOG se inserta en su sitio**, encima de la última
  versión, en vez de por delante de la cabecera introductoria del documento.

### DOCUMENTACIÓN

- `docs-dev/VERSIONING.md` reescrito: fuente de verdad, criterios
  MAJOR/MINOR/PATCH con ejemplos del propio proyecto, consulta, bump, despliegue
  y compatibilidad.
- `README.md`, `CLAUDE.md` y `docs/ENVIRONMENT.md` actualizados; se retiraron
  las versiones fijas obsoletas (`2.2.0`, `3.3.0`) que ya no coincidían con el
  código.

---

## [3.4.0] - 2026-08-10

### CORREGIDO

Restauración del esquema de `api_token_requests`, que quedó desalineado del
código y dejó inutilizable el formulario público `/solicitar-token`.

- **Columnas restauradas** (migración `2026_08_10_000001_restore_api_token_requests_secure_schema`).
  La migración `2026_08_07_000001` eliminó columnas que la aplicación seguía
  usando: `tracking_code`, `requester_name_encrypted`,
  `requester_email_blind_index`, `requester_phone_encrypted`,
  `purpose_encrypted`, `delivery_method_encrypted`, `delivery_reason_encrypted`,
  `token_hash`, `token_last_four`, `token_revealed_at`,
  `token_revealed_by_type` y `token_revealed_by_user_id`. La nueva migración es
  idempotente y no toca ningún dato existente.
- **`tracking_code` unificado** como `varchar(20)` con índice único, para el
  formato vigente `CR-` + 10 caracteres (13 en total). Las filas anteriores se
  rellenan reutilizando el código guardado en `metadata->tracking_code` y, si no
  existe, generando uno nuevo.
- **`encrypted_plain_text_token` → `token_ciphertext`.** Convivían los dos
  nombres: el panel de administración cifraba en uno y las purgas (rechazo,
  cancelación, caducidad, revocación y entrega) limpiaban el otro, de modo que
  el token cifrado podía sobrevivir a su propio borrado. Ahora existe una sola
  columna y `TokenVaultService::decryptToken()` descifra también los registros
  heredados cifrados con `APP_KEY`.
- **Rotaciones cifradas con la clave del vault** en lugar de `APP_KEY`, y se
  guardan `token_hash` y `token_last_four` igual que en una emisión normal.
- **`api_token_request_events.api_token_request_id` pasa a ser nullable**: las
  consultas públicas fallidas se auditan sin solicitud asociada y provocaban un
  error 500 en cada búsqueda sin resultados.
- **`TokenRequestAuditLog` apuntaba a una tabla inexistente**
  (`token_request_audit_logs` en vez de `api_token_request_audit_logs`), lo que
  rompía todo el registro de auditoría de OTP y revelación de tokens.
- **Campos cifrados asignables en masa.** `requester_name`, `requester_phone`,
  `purpose`, `delivery_method` y `delivery_reason` no estaban en `$fillable`, así
  que las solicitudes creadas desde n8n se guardaban sin nombre ni motivo; ahora
  además aceptan valores nulos sin reventar al cifrar.
- **`DB::transaction(..., maxAttempts: 3)`** usaba un nombre de parámetro
  inexistente y lanzaba `Error` al confirmar una entrega o revelar un token.
- **`app:bump-version` corrompía `config/version.php` y `config/app.php`**: la
  retrorreferencia `$1` seguida del número se interpretaba como `$13`.

### AÑADIDO

- Factories `ApiTokenRequestFactory` y `ApiTokenFactory`.
- Cobertura de creación y consulta pública de solicitudes: formato y
  persistencia de `tracking_code`, cifrado e indexado ciego de los datos del
  solicitante, y búsqueda por código + correo (incluido el caso sin resultados).
- `update.sh` verifica tras migrar que `api_token_requests` conserva las
  columnas que el código necesita y que no sobrevive la columna heredada.

---

## [3.2.0] - 2026-08-09

### RENDIMIENTO

Optimizacion del listado del padron (/admin/ruc) para 18M+ registros. Todas
las cifras estan medidas con EXPLAIN (ANALYZE, BUFFERS) sobre **18 000 000 de
filas reales**; el detalle completo esta en `docs-ruc/LIST_PERFORMANCE.md`.

- **Indices de filtro sustituidos por compuestos `(columna, id)`**
  (migracion `2026_08_09_000004_optimize_ruc_records_list_indexes`). La
  consulta del listado es siempre `WHERE <filtro> = ? ORDER BY id LIMIT 51`, y
  un indice de una sola columna no sirve para esa forma: PostgreSQL tiene que
  ordenar despues, asi que acaba recorriendo la clave primaria y descartando
  millones de filas.

  | Consulta | Antes | Despues |
  |---|---:|---:|
  | `provincia` + `condicion` | 10 943 ms | **0.31 ms** |
  | `distrito` + `estado` | 1 131 ms | **2.46 ms** |
  | `ubigeo` + `departamento` | 792 ms | **8.09 ms** |
  | `distrito` (solo) | 106 ms | **1.48 ms** |
  | `provincia` (solo) | 97 ms | **9.83 ms** |

- **Seis indices de una sola columna eliminados** (~681 MB). `estado` (4
  valores), `condicion` (3) y `departamento` (8) no son lo bastante selectivos
  para que el planificador los elija — se comprobo `idx_scan = 0` — y sin ellos
  las consultas siguen en 0.3-4.8 ms. `provincia`, `distrito` y `ubigeo` se
  sustituyen por su version compuesta. El total de indices pasa de ~2.5 GB a
  ~3.7 GB: es el precio de convertir un peor caso de 11 s en uno de 10 ms.

- **`VACUUM ANALYZE` tras cada carga masiva**, no solo `ANALYZE`. El indice GIN
  de `razon_social` acumula entradas en su *pending list* durante una carga
  masiva y cada busqueda la recorre linealmente hasta que se vacia: 6 996 ms
  antes del VACUUM, 1 148 ms despues. Se aplica en
  `RucChunkedRestoreService` sobre la tabla de staging (todavia no atiende
  consultas) y en `update.sh`.

- **`pg_trgm` evaluado y conservado**: 810-1 226 ms con el indice frente a
  11 403 ms forzando escaneo secuencial, y 2 ms en el caso habitual de un
  termino comun. Se justifica.

### AGREGADO
- El listado muestra el total del padron leyendo `ruc_statistics`, **nunca con
  `COUNT(*)`** (que cuesta ~8 s sobre 18M y ningun indice evita). El metadato
  lo actualiza `RucStatisticsService` al terminar cada restauracion.
- El RUC del listado enlaza al detalle, que es donde se cargan las columnas
  que la lista no trae: desglose de la direccion (`tipo_via`, `nombre_via`,
  `numero`, `interior`, `lote`, `manzana`, `kilometro`,
  `departamento_direccion`, `tipo_zona`, `codigo_zona`).
- `docs-ruc/LIST_PERFORMANCE.md` con la metodologia, los planes y los numeros.

### CORREGIDO
- `RucListPerformanceTest` llevaba tiempo fallando por completo con "The
  response is not a view": comprobaba un componente Livewire de pagina completa
  mediante una peticion HTTP, y en ese caso la respuesta es el layout, no la
  vista del componente. Reescrito con `Livewire::test()`; pasa de 6 pruebas
  rotas a 14 en verde, que ademas verifican lo que antes solo se afirmaba
  (que no hay `COUNT`, que no hay `DISTINCT`, que el RUC exacto usa igualdad y
  no `ILIKE`, que el cursor no solapa ni salta registros).

### PENDIENTE CONOCIDO
- `GET /api/v1/ruc/buscar` tarda **~20 s** con un termino comun (9 766 ms de
  `COUNT(*)` que introduce `paginate()` + 10 481 ms de `ORDER BY razon_social`).
  No se ha cambiado porque `total` y `last_page` forman parte del contrato
  publico documentado y los consume la extension de Chrome.

## [3.1.0] - 2026-08-09

### AGREGADO
- **Formato `.rucbackup`: backup del padrón en un solo archivo, troceado por
  dentro.** Contenedor ZIP64 con `manifest.json` + `chunks/NNNNNN.csv.zst`.
  No se generan archivos externos por lote: para el operador sigue habiendo un
  unico fichero. Se elige ZIP64 porque su directorio central permite abrir el
  chunk N sin leer los N-1 anteriores, que es lo que hace barata la
  reanudacion; con `tar.zst` o un gzip unico el coste de reanudar creceria con
  el avance. Ver `docs-ruc/BACKUP_FORMAT.md`.
- **Backup por lotes con memoria constante.** Cada lote se genera con
  `psql \copy ... TO STDOUT | zstd`: ninguna fila pasa por PHP. Medido: 36.5 MB
  de RAM con 100k, 1M y con cualquier tamano de lote. Se avanza con paginacion
  por clave (`WHERE id > ultimo`), no con `OFFSET`, para que el coste por lote
  sea constante.
- **Restauracion por lotes, reanudable y sin exponer la tabla activa.** Los
  datos se cargan en `ruc_records_next` mediante `COPY FROM STDIN`; `ruc_records`
  sigue sirviendo consultas durante toda la carga y solo se sustituye al final
  con un intercambio de nombres instantaneo. Si un lote falla, no hay swap y la
  tabla activa queda intacta.
- **Checkpoints por lote** en `ruc_backup_operations` (`current_batch`,
  `completed_batches`, `records_processed`, `bytes_processed`, `staging_table`,
  `checkpoint`) — migracion `2026_08_09_000003_add_chunked_restore_checkpoints`.
- **`ruc:restore --resume`**: continua desde el ultimo lote confirmado. Nunca
  salta un lote a ciegas: valida que el staging exista, que el `chunks_sha256`
  coincida con el del archivo y que el numero de filas cuadre con el checkpoint.
- **`ruc:restore-manage`**: `--status`, `--cancel`, `--discard-staging`,
  `--rollback`. La cancelacion se atiende entre lotes, nunca a mitad de un COPY.
- **Validacion previa al restore**: formato, version, columnas contra el esquema
  real, numeracion de chunks sin huecos, checksum SHA-256 por chunk y espacio en
  disco. Un chunk corrupto se rechaza ANTES de crear la tabla de staging.
- `docs-ruc/BACKUP_FORMAT.md` con estructura, decisiones de diseno y benchmarks.
- `tests/Feature/Ruc/RucChunkedBackupTest.php` — 19 pruebas: 1 lote, varios
  lotes, ultimo lote incompleto, checksum incorrecto, chunk faltante, manifest
  incoherente, version no soportada, columnas distintas, fallo en lote
  intermedio, resume, resume con backup distinto, resume con staging
  inconsistente, cancelacion, swap, indices y rollback.

### CAMBIADO
- `ruc:backup` genera `.rucbackup` en lugar de `.dump`. El flag `--legacy`
  mantiene el formato antiguo para casos puntuales.
- `ruc:restore` acepta un ID de backup **o una ruta** a un `.rucbackup`, y
  detecta el formato por CONTENIDO (presencia de `manifest.json`), no por la
  extension. Los `.dump` ya existentes se siguen restaurando por el camino
  legacy sin cambios.
- `docker/php/Dockerfile` declara `zstd` explicitamente: estaba disponible como
  dependencia indirecta y una actualizacion de la imagen base podria haberlo
  dejado fuera.
- `update.sh` comprueba `zstd` y la extension PHP `zip`, y avisa si encuentra
  restos de una restauracion troceada (`ruc_records_next` / `ruc_records_old`).

## [3.0.0] - 2026-08-09

### ELIMINADO (BREAKING)

Se retira por completo el **sistema de importación RUC**. La administración del
padrón pasa a hacerse exclusivamente mediante **backup y restore** de
`ruc_records`, que queda como fuente permanente.

**Los datos NO se tocan:** `ruc_records`, `ruc_backups`, `ruc_backup_operations`
y los backups en disco se conservan intactos. La migración de limpieza no
ejecuta `TRUNCATE` ni `DELETE` sobre el padrón.

- **Interfaz** — se elimina la pantalla de importaciones RUC, sus componentes
  Livewire (`Imports`, `ImportManager`, `ImportMonitor`) y el enlace del menú,
  reemplazado por "Backups RUC". El dashboard deja de mostrar métricas de
  importación.
- **Rutas** — `admin.ruc.imports` y `admin.ruc.imports.errors`.
- **Comandos** — `ruc:scan`, `ruc:import`, `ruc:pause`, `ruc:resume`,
  `ruc:cancel`, `ruc:status`, `ruc:cleanup`, `ruc:has-active`,
  `ruc:import-status`, `ruc:cleanup-imports`.
- **Jobs** — `PrepareRucImportJob`, `ProcessRucImportJob`, `ProcessRucImportJobV3`.
- **Clases** — 4 modelos, 3 enums, 1 policy, 1 evento, 5 objetos de datos,
  14 servicios y 2 helpers de soporte exclusivos de importación.
- **Tablas** (migración `2026_08_09_000001_drop_ruc_import_tables`) —
  `ruc_imports`, `ruc_import_errors`, `ruc_import_events`,
  `ruc_import_duplicates`, `ruc_staging`. También la columna huérfana
  `ruc_records.ruc_import_id` (su clave foránea impedía el DROP; en PostgreSQL
  `DROP COLUMN` es metadata-only y no reescribe la tabla) y
  `ruc_statistics.total_imports`.
- **Cola** — conexión `ruc-imports` en `config/queue.php` y su cola en el worker
  general. `ruc-backups` y su worker dedicado se mantienen sin cambios.
- **Configuración** — bloque `ruc.import` completo y todas las variables
  `RUC_IMPORT_*` de `.env.example`, del instalador y de la documentación.
- **Permisos** — `ruc.import`, `ruc.import-history`, `ruc.delete-import-file`,
  `ruc.cancel-import`, `ruc.view-errors` salen del seeder. Las filas ya
  existentes en base de datos NO se borran.
- **Eventos de integración** — `ruc.import.started`, `ruc.import.progress` y
  `ruc.import.finished` salen del catálogo `EventType`. Ningún productor los
  emitía, pero cualquier workflow n8n suscrito debe actualizarse.
- **Documentación** — se eliminan `DEPLOYMENT_RUC_V3.md` y los cuatro
  `RUC_IMPORT_V3_*.md`. El ADR 0039 se conserva marcado como **superado**.

### CORREGIDO
- **`RestoreRucBackupJob` fallaba siempre al terminar.** `DB::statement('ANALYZE
  ruc_records')` se usaba sin importar el facade `Illuminate\Support\Facades\DB`,
  así que PHP resolvía `App\Modules\Ruc\Jobs\DB` y lanzaba un `Error` fatal
  **después** de restaurar los datos correctamente, dejando la operación marcada
  como `failed` pese a que el padrón sí se había restaurado. Bug preexistente,
  no introducido por esta limpieza.
- `RucStatisticsService` contaba `ruc_imports`, lo que habría roto todo restore
  al soltar la tabla.

### AGREGADO
- Enlace directo a **Backups RUC** en la navegación lateral (antes la pantalla
  de backups no era alcanzable desde el menú).
- `tests/Feature/Ruc/RucImportSystemRemovedTest.php` — 14 pruebas que fijan la
  eliminación (rutas, tablas, clases, configuración) y verifican que
  `ruc_records`, sus columnas y sus datos siguen intactos.

## [2.3.1] - 2026-08-09

### CORREGIDO
- **RUC Backup/Restore — `UrlGenerationException` en `/admin/ruc/backups`**
  - La vista construia la URL de polling llamando a
    `route('admin.ruc.backups.operations.status', ['operation' => ''])` y
    concatenaba el UUID en JavaScript. Laravel descarta los parametros con
    valor vacio, asi que `{operation}` quedaba sin resolver y la pagina
    respondia HTTP 500 — pero solo cuando existia una restauracion activa,
    porque el bloque vive dentro de `@if($activeRestoreOperation)`.
  - Ahora la URL se genera en servidor con la operacion concreta
    (route model binding por `uuid`) y se pasa ya resuelta a Alpine.
  - El polling se detiene en estado terminal, ante 404/410 y al desmontar el
    componente; ya no se recarga la pagina cuando el restore falla, para no
    ocultar el error.
  - El restore fallido/completado sigue visible al recargar mediante un panel
    estatico (`RucBackupOperation::latestFinishedRestore()`), sin polling.
  - `RucBackupOperation::toStatusPayload()` unifica la forma del estado entre
    el endpoint JSON y el render inicial: `backup_name` ya no aparece en
    blanco hasta el primer poll.

### AGREGADO
- `tests/Feature/Ruc/RucBackupRestoreStatusUiTest.php` — 10 pruebas de
  regresion: pagina sin operacion, restore `pending`, `running`, `completed`
  y `failed`, guard de la ruta sin parametro, paridad del payload, y
  verificacion de que recargar la pagina no crea ni reinicia operaciones.

## [2.2.0] - 2026-08-06

### ✨ AGREGADO
- **RUC Import System v3.0** — Arquitectura completamente rediseñada
  - Procesamiento streaming de archivos ilimitados con O(1) memoria constante
  - Event sourcing completo para auditoría en tabla `ruc_import_events`
  - Tracking de duplicados en tabla `ruc_import_duplicates`
  - Progreso en tiempo real mediante broadcasting (actualización <1s)
  - Rollback automático con reversión segura de transacciones
  - Checkpoints transaccionales para recuperación ante interrupciones
  - Validación granular por línea con reportes detallados
  - Estrategias merge configurable (Insert, Insert-Update, Replace)
  - Pausa/Reanudación de importaciones en progreso
  - Cancelación segura manteniendo integridad transaccional
  - 7 servicios modulares para orquestación completa
  - 2 componentes Livewire 3 para UI en tiempo real

- **Migraciones v3.0**
  - Tabla `ruc_import_events` con índices de rendimiento
  - Tabla `ruc_import_duplicates` con unique constraints
  - 20+ nuevas columnas en `ruc_imports` para tracking
  - 4 campos nuevos en `ruc_import_errors` para resolución

- **Endpoints API RUC v3.0**
  - `POST /admin/ruc/imports` — Crear importación
  - `GET /admin/ruc/imports` — Listar importaciones
  - `GET /admin/ruc/imports/{id}` — Obtener detalles
  - `GET /admin/ruc/imports/{id}/progress` — Progreso en tiempo real
  - `POST /admin/ruc/imports/{id}/pause` — Pausar
  - `POST /admin/ruc/imports/{id}/resume` — Reanudar
  - `POST /admin/ruc/imports/{id}/cancel` — Cancelar
  - `POST /admin/ruc/imports/{id}/rollback` — Revertir
  - `GET /admin/ruc/imports/{id}/errors/download` — Descargar errores

- **Documentación v3.0**
  - `RUC_IMPORT_V3_IMPLEMENTATION.md` — Guía exhaustiva (2,500+ palabras)
  - `RUC_IMPORT_V3_QUICK_START.md` — Configuración en 60 segundos
  - `RUC_IMPORT_V3_CHECKLIST.md` — Validación de despliegue
  - `DEPLOYMENT_RUC_V3.md` — Guía de actualización con 12 pasos
  - `RUC_IMPORT_NEW_ARCHITECTURE.md` — Diseño detallado
  - `RUC_IMPORT_AUDIT.md` — Auditoría de v2.0

- **Sistema de versionado automático**
  - Versionado semántico (major.minor.patch)
  - Detección automática por tipo de commit (feat, fix, BREAKING)
  - Script artisan `app:bump-version` para actualización manual
  - CHANGELOG.md consolidado

### 📊 MEJORAS DE RENDIMIENTO
- **Velocidad**: 10x más rápido (1K → 10K registros/segundo)
- **Memoria**: 4x menos pico (512MB → 128MB)
- **Escalabilidad**: Archivos ilimitados (probado hasta 10GB+)
- **E/S**: 3x menos operaciones de base de datos

### 🔒 SEGURIDAD
- Tokens de una sola vez con `lockForUpdate()` para prevenir condiciones de carrera
- Hash bcrypt para tokens OTP
- Encriptación AES-256-CBC para datos personales
- Transacciones atómicas para reversión segura
- Autorización basada en políticas granular

### 📚 DOCUMENTACIÓN
- README.md actualizado con sección RUC v3.0
- Consolidación de CHANGELOG.md maestro
- Reorganización de documentación en estructura clara
- Guía de actualización simplificada con `./update.sh`

### 🛠️ DEVOPS
- `update.sh` automatiza 12 pasos de despliegue
- Respaldo automático de `.env`
- Construcción selectiva de imágenes Docker
- Verificación automática de salud post-despliegue
- Lista de verificación pre-despliegue incluida

---

## [2.1.0] - 2026-08-05

### ✨ AGREGADO
- Registros de Auditoría de Solicitudes de Tokens API (`ruc_import_audit_logs`)
- Tabla de Validaciones OTP para validación de códigos
- Seguridad mejorada en solicitudes de tokens API
- Campos adicionales en `api_token_requests` para seguimiento de entrega segura

### 🔒 SEGURIDAD
- Auditoría completa de solicitudes de tokens
- Validación de OTP con encriptación
- Protección de contacto de entrega (enmascarado)

---

## [2.0.0] - 2026-08-01

### ✨ AGREGADO
- RUC Import System v2.0 (arquitectura anterior)
- Procesamiento por lotes con tabla de almacenamiento temporal
- Registro de eventos básico
- Soporte para múltiples estrategias de fusión

### ⚠️ NOTA
- **Deprecado**: Versión reemplazada por v3.0 en 2026-08-06
- Limitaciones: máximo 2GB, alto consumo de memoria, sin progreso en tiempo real

---

## [1.0.0] - 2026-07-01

### ✨ AGREGADO
- Lanzamiento inicial de CodeRED Platform
- Gestión de agencias Shalom
- APIs para DNI, RUC y Agencias
- Integración n8n con CodeRED Agent
- Solicitudes de tokens API con aprobación
- Extensión Chrome Buscador Shalom
- Panel de administración

---

## Notas de versiones anteriores

Para cambios específicos en módulos, ver:
- `docs/changelog/TOKEN_REQUESTS.md` — Solicitudes de tokens API
- `docs/changelog/RUC_V3.md` — RUC Import System v3.0 en detalle
- `docs/CHANGELOG.md` — Registro de cambios histórico (deprecado, usar este archivo)
