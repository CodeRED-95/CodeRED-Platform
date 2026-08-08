# Sistema de Backup y Restauración RUC

Backup/restore de la tabla `ruc_records`. Solo eso: crear backup, descargar,
importar uno existente y restaurar. Sin colas, sin S3, sin incrementales,
sin JavaScript interceptando formularios.

**Diseño deliberadamente simple:** formularios HTML tradicionales (`POST`
con `@csrf`), sin Livewire ni `fetch()` para las acciones críticas. El
navegador hace un POST normal, Laravel responde con un `redirect()` y un
mensaje flash. Esto elimina de raíz la clase de bugs (419, dobles submits,
`x-teleport`, listeners que no se enganchan a tiempo) que tenía la
implementación anterior.

---

## Qué hace

| Acción | Ruta | Método |
|---|---|---|
| Ver panel | `/admin/ruc/backups` | `GET` |
| Crear backup | `/admin/ruc/backups` | `POST` |
| Importar backup | `/admin/ruc/backups/import` | `POST` (multipart) |
| Descargar backup | `/admin/ruc/backups/{backup}/download` | `GET` |
| Restaurar backup | `/admin/ruc/backups/{backup}/restore` | `POST` |
| Eliminar backup | `/admin/ruc/backups/{backup}` | `DELETE` |

Controlador: `App\Modules\Ruc\Http\Controllers\RucBackupController`.
Lógica: `App\Modules\Ruc\Services\RucBackupService`.
Modelo: `App\Modules\Ruc\Models\RucBackup` (tabla `ruc_backups`).
Vista: `resources/views/ruc/admin/backups/index.blade.php`.

## Permisos

| Permiso | Acción |
|---|---|
| `ruc.backup.view` | Ver el panel y la lista |
| `ruc.backup.create` | Crear e importar backups |
| `ruc.backup.download` | Descargar |
| `ruc.backup.restore` | Restaurar |
| `ruc.backup.delete` | Eliminar |

---

## Formato del archivo

Los backups son dumps de `pg_dump` en **formato custom, solo datos**
(`--format=custom --data-only --table=ruc_records`) — **no** son archivos
gzip reales, aunque algunos backups antiguos del sistema anterior se
llamaron `*.sql.gz` (firma real: `PGDMP`, no `\x1f\x8b`).

- **Backups nuevos:** extensión `.dump` (p. ej.
  `ruc_backup_2026-08-08_143000_ab12cd34.dump`).
- **Backups antiguos `.sql.gz`:** se siguen sirviendo, descargando y
  restaurando exactamente igual. El sistema **nunca confía en la
  extensión** — todo archivo (nuevo o antiguo) se valida por contenido con
  `pg_restore --list` antes de aceptarse como backup o antes de restaurarse.

### Por qué solo datos (`--data-only`), no schema completo

El schema de `ruc_records` es responsabilidad de las migrations de
Laravel, no del backup. Un dump con schema (`CREATE TABLE`) forzaría a
elegir entre truncar la tabla existente (y toparse con "relation already
exists" al intentar recrearla) o usar `--clean` para dropearla y
recrearla — lo cual reintroduce el schema tal como estaba en el momento
del backup, deshaciendo silenciosamente cualquier migration aplicada
después. `ruc_records` no tiene ninguna foreign key entrante (verificado
contra `information_schema`), así que no hay ninguna dependencia externa
que justifique tocar el schema. Con `--data-only`, un restore SIEMPRE dejará
la estructura de la tabla exactamente como la definieron las migrations,
sin importar cuándo se tomó el backup.

## Ubicación de almacenamiento

Los archivos viven bajo el disco `local` de Laravel
(`config/filesystems.php`), en el directorio relativo `backups/ruc/`.

`ruc_backups.storage_path` guarda **siempre una ruta relativa a ese disco**
(p. ej. `backups/ruc/ruc_backup_2026-08-08_143000_ab12cd34.dump`), nunca una
ruta absoluta — así el sistema no depende de dónde vive el contenedor. Para
obtener la ruta real en cualquier punto del código:

```php
$backup->absolutePath(); // Storage::disk('local')->path($backup->storage_path)
```

El root real del disco `local` es `storage/app/private` desde Laravel 11
(antes era `storage/app` a secas) — por eso nunca se debe construir la ruta
a mano con `storage_path('app/'.$path)`.

---

## Uso desde el panel

1. **Crear Backup** — botón, ejecuta `pg_dump` contra `ruc_records` y
   registra el resultado.
2. **Importar Backup** — dos pestañas:
   - **Backup completo**: selecciona un archivo `.dump` (o `.gz` legado) y
     lo sube en un solo request. Se valida por contenido, se calcula
     SHA-256, se guarda.
   - **Backup dividido**: importa un backup generado por
     [packages/ruc-tools](../../../packages/ruc-tools) (`manifest.json` +
     `*.partNNNN` de ~90 MiB cada uno). Ver "Importación multipart" abajo.

   En ambos casos: **no restaura automáticamente** — importar y restaurar
   son pasos separados.
3. **Descargar** — descarga el archivo tal cual.
4. **Restaurar** — reemplaza el contenido de `ruc_records` con el del
   backup. Antes de tocar nada, crea automáticamente un backup de seguridad
   del estado actual (ver abajo). La confirmación usa
   `<x-ui.confirm-dialog>` del Design System (ver `/admin/design-system`),
   **no** el `confirm()` nativo del navegador — sigue siendo un `<form
   method="POST">` con `@csrf` real; Alpine solo abre/cierra el diálogo y
   dispara `form.requestSubmit()` al confirmar. Si ya hay una restauración
   en curso, la segunda queda bloqueada (`Cache::lock('ruc-restore-process')`)
   en vez de ejecutarse en paralelo sobre la misma tabla.
5. **Eliminar** — borra el registro y el archivo. Misma confirmación vía
   `<x-ui.confirm-dialog>` (`tone="danger"`).

## Uso desde consola

```bash
# Crear backup
docker compose exec app php artisan ruc:backup

# Listar backups
docker compose exec app php artisan ruc:backups-list
docker compose exec app php artisan ruc:backups-list --status=failed

# Restaurar (pide confirmación interactiva)
docker compose exec app php artisan ruc:restore <backup_id>
```

## Uso desde código

```php
use App\Modules\Ruc\Services\RucBackupService;

$service = app(RucBackupService::class);

$backup = $service->create($user);              // crear
$service->import($absolutePath, $originalName, $user); // importar (ya guardado en disco)
$result = $service->restore($backup, $user);     // restaurar
// $result: ['records_restored' => int, 'safety_backup_id' => int, 'duration_seconds' => int]
```

---

## Backup de seguridad (safety backup)

Antes de CUALQUIER restore, el sistema crea automáticamente otro backup del
estado actual de `ruc_records` (`backup_type = 'safety'`, nombre
`ruc_safety_before_restore_YYYY-MM-DD_HHmmss.dump`). Si ese backup de
seguridad falla por cualquier razón, **el restore se aborta antes de tocar
la base de datos** — nunca se restaura sin tener antes una forma de volver
atrás.

## Restauración atómica

`restore()` NO hace `TRUNCATE` seguido de `pg_restore --single-transaction`
como pasos independientes — eso NO es atómico de verdad, porque
`pg_restore --single-transaction` abre su propia conexión/transacción y no
puede anidarse dentro de una transacción abierta por Laravel desde otra
conexión. Si lo fueran, un fallo de `pg_restore` dejaría el `TRUNCATE` ya
confirmado y la tabla vacía.

En su lugar:

1. `pg_restore --data-only -f archivo.sql backup.dump` convierte el dump a
   SQL plano **sin tocar ninguna base de datos**.
2. Se arma un script `wrapper.sql`:
   ```sql
   BEGIN;
   TRUNCATE TABLE ruc_records;
   \i archivo.sql
   COMMIT;
   ```
3. Se ejecuta con **una sola sesión de `psql -v ON_ERROR_STOP=1`**.

Si el `COPY` de los datos falla, `psql` nunca llega al `COMMIT`; al cerrar
la conexión, PostgreSQL revierte automáticamente TODA la transacción —
incluido el `TRUNCATE` — dejando `ruc_records` exactamente como estaba.
Verificado manualmente con un dump truncado a mitad de archivo: la tabla
queda intacta.

`pg_restore`/`pg_dump` siempre usan `--no-owner --no-privileges`: los
objetos restaurados quedan de propiedad de la conexión configurada de la
app, y ningún `GRANT`/`REVOKE`/`ALTER ... OWNER` del archivo se aplica. Y
`--single-transaction` nunca se combina con `--jobs` (son mutuamente
excluyentes en `pg_restore`).

## Seguridad del contenido del dump

Antes de aceptar un backup (al importar) o de usarlo para restaurar,
`RucBackupService::assertDumpBelongsToRucRecords()` ejecuta
`pg_restore --list` y revisa **cada objeto** del archivo (tablas,
secuencias, datos, índices, constraints). Se acepta cualquier objeto cuyo
nombre sea exactamente `ruc_records`/`ruc_records_id_seq`, o que **empiece
con el prefijo `ruc_records_`** (cubre los nombres que PostgreSQL genera
automáticamente para índices/constraints propios de la tabla, como
`ruc_records_pkey` o `ruc_records_condicion_index`). Si aparece cualquier
otro objeto, el archivo se rechaza por completo — así un dump de otra tabla
(`users`, `agencies`, `ruc_backups`, lo que sea), subido por un usuario con
permiso de importar, nunca puede usarse para tocar nada fuera de
`ruc_records`. Verificado contra `pg_tables`: ninguna otra tabla del
esquema comparte ese prefijo, así que el match no amplía la superficie de
ataque.

---

## Importación multipart (RUC Tools)

[packages/ruc-tools](../../../packages/ruc-tools) (herramienta **local**,
nunca desplegada aquí — ver su propio README) genera backups divididos en
partes de ~90 MiB: `manifest.json` + `*.dump.partNNNN`. La razón de
transportarlos así es evitar el límite de tamaño de request de Cloudflare
(~100 MB en proxied) al subirlos de vuelta a producción — un `.dump` de
cientos de MB o varios GB nunca cabría en un solo POST.

### Por qué no se reconstruye el archivo en el navegador

Reconstruir el `.dump` completo en el cliente (`new Blob([...])`) y subirlo
de una sola vez volvería a chocar exactamente con el mismo límite de
Cloudflare que este mecanismo existe para evitar. En cambio, **cada parte
se sube en un request HTTP independiente** (`resources/js/
ruc-backup-multipart-uploader.js`, vía `XMLHttpRequest` — no `fetch`, para
poder leer `xhr.upload.onprogress` y mostrar progreso real). El ensamblado
final ocurre siempre en el servidor.

### Flujo

1. El usuario selecciona el `manifest.json` en la pestaña "Backup dividido"
   de `/admin/ruc/backups`. El frontend lo parsea y muestra su metadata
   (registros, tamaño, número de partes, SHA-256) — validación básica solo
   para feedback inmediato, **nunca la fuente de verdad**.
2. El usuario selecciona las partes (`<input type="file" multiple>`); se
   reordenan automáticamente por el índice en su nombre.
3. `POST /admin/ruc/backups/multipart` crea la sesión
   (`RucBackupUpload` + una fila `RucBackupUploadPart` por parte
   esperada), revalidando el manifest íntegramente en el servidor
   (`RucBackupMultipartUploadService::assertManifestIsWellFormed()`):
   `format_version` soportado, `backup_type === 'ruc_records'`,
   `total_parts`/`total_size_bytes`/`part_size_bytes` dentro de límites
   configurables, nombres de archivo saneados (solo `basename`, nunca una
   ruta), índices `1..N` consecutivos, `sha256` con formato válido.
4. Cada parte se sube por separado: `POST /admin/ruc/backups/multipart/
   {uuid}/parts/{index}`. El servidor valida propietario de la sesión,
   índice esperado, **nombre de archivo declarado** (debe coincidir con el
   del manifest), tamaño exacto, y `hash_file('sha256', ...)` de la parte
   recibida contra el checksum del manifest — si no coincide, `422` con
   `"Checksum incorrecto en partNNNN."` y la parte no se guarda como
   verificada. La parte se guarda en disco con un nombre **generado por el
   servidor** (`partNNNN.bin`), nunca con el filename que mandó el
   cliente.
5. Al recibir la última parte pendiente, el servidor ensambla el `.dump`
   final por streaming (`fopen`/`stream_copy_to_stream`, nunca
   `file_get_contents`), en el orden `part0001..partNNNN`.
6. Valida: tamaño total == `manifest.total_size_bytes`, SHA-256 total ==
   `manifest.sha256`, y `pg_restore --list` (misma validación de contenido
   que un import de un solo archivo — rechaza cualquier tabla que no sea
   `ruc_records`).
7. Si todo pasa: registra un `RucBackup` normal (`backup_type = 'uploaded'`),
   guarda una copia del `manifest.json` junto al backup (auditoría), y
   borra las partes temporales. A partir de ahí, Download/Restore/Delete
   funcionan exactamente igual que cualquier otro backup.

### Reanudar y cancelar

`GET /admin/ruc/backups/multipart/{uuid}` devuelve qué partes ya están
verificadas — si el usuario recarga la página o vuelve más tarde, el
frontend guarda el `upload_uuid` en `localStorage` (indexado por el
`sha256` del manifest) y continúa solo con las partes que faltan, sin
volver a subir las ya verificadas. `DELETE /admin/ruc/backups/multipart/
{uuid}` cancela una sesión y borra sus partes temporales — nunca toca un
backup ya completado.

### Reintentos

Cada parte reintenta hasta 3 veces (backoff 1s/3s/5s) ante errores de red.
Un `422` (checksum/tamaño/permiso incorrecto) es un rechazo definitivo del
servidor y no se reintenta — reintentar no lo arreglaría.

### Limpieza automática

`php artisan ruc:cleanup-backup-uploads` (programado cada hora, ver
`routes/console.php`) cancela y borra las partes de sesiones que superaron
su expiración (`ruc.backup.multipart.session_expires_hours`, default 24h)
sin completarse. Nunca toca sesiones ya completadas ni sus backups.

### Seguridad específica de multipart

- El manifest se revalida por completo en el servidor — el frontend nunca
  es la fuente de verdad.
- Ningún nombre de archivo del cliente (manifest ni parte individual) se
  usa como ruta de almacenamiento: se sanea a `basename()` y se compara,
  pero el archivo en disco siempre usa un nombre generado por el servidor.
- Límites configurables (`config('ruc.backup.multipart')`):
  `max_part_size_mb` (techo duro independiente de lo que declare el
  manifest), `max_total_parts`, `max_total_size_mb`.
- Antes de crear la sesión se verifica espacio en disco libre
  (~2x `total_size_bytes`, ya que durante el ensamblado coexisten todas
  las partes + el archivo final).
- Una sesión de subida pertenece a un único usuario; ninguna otra cuenta
  puede leer su estado, subirle partes, ni cancelarla.

---

## Límites de archivo

`RUC_BACKUP_MAX_UPLOAD_MB` (`config/ruc.php` → `ruc.backup.max_upload_mb`,
default `5000` = 5 GB) controla el límite de Laravel para **importar** un
backup. Ese número **debe ser ≤** los límites reales de la infraestructura,
o Laravel nunca llega a rechazarlo con un mensaje claro — PHP/Nginx cortan
antes:

| Capa | Directiva | Valor actual |
|---|---|---|
| PHP | `upload_max_filesize` (`docker/php/php.ini`) | `5G` |
| PHP | `post_max_size` (`docker/php/php.ini`) | `5100M` |
| PHP | `max_execution_time` (`docker/php/php.ini`) | `0` (sin límite) |
| Nginx | `client_max_body_size` (`docker/nginx/default.conf`) | `5G` |

Si necesitas subir el límite, cambia **las cuatro** al mismo tiempo
(`RUC_BACKUP_MAX_UPLOAD_MB`, `upload_max_filesize`, `post_max_size`,
`client_max_body_size`) y reconstruye `app`/`nginx`. Subir solo uno no
sirve de nada.

`pg_dump`/`pg_restore`/`psql` se ejecutan con `Process::setTimeout(null)`
(sin límite): una tabla de millones de filas puede tardar minutos, y no
tiene sentido que un timeout artificial mate la operación a mitad de
camino.

## Archivos grandes: nunca en memoria de PHP

- **Checksum:** `hash_file()` (streaming, no `file_get_contents()`).
- **Descarga:** `response()->download()` → `BinaryFileResponse`, transmite
  el archivo directo a disco, no lo carga en memoria PHP.
- **Subida:** Laravel escribe el upload a un archivo temporal en disco
  (comportamiento estándar de PHP), nunca lo mantiene completo en memoria.
- **pg_dump / pg_restore / psql:** procesos externos que leen/escriben
  directo a archivos; PHP solo espera a que terminen y lee su código de
  salida y `stderr` (nunca su `stdout` completo para archivos de datos).

---

## Troubleshooting

### 413 Request Entity Too Large / Cloudflare al importar un backup grande

Si `platform.codered.host` está detrás de Cloudflare (proxied, nube
naranja), Cloudflare corta requests grandes (~100 MB en free/pro) **antes**
de que lleguen a nginx — subir un `.dump` de cientos de MB en un solo
request siempre dará 413, sin importar qué tan generoso sea
`client_max_body_size`. La solución no es subir el límite de nginx (ya es
generoso, ver "Límites de archivo" abajo): es usar un **backup multipart**
(ver "Importación multipart" arriba) generado con `ruc-tool backup
--part-size=90`, que sube cada parte (~90 MiB) en su propio request,
siempre por debajo del límite de Cloudflare.

### 419 Page Expired

Causa raíz del problema original: la implementación anterior interceptaba
el `submit` del formulario con JavaScript (`fetch`, `addEventListener`,
Alpine dentro de un modal con `x-teleport`). Cuando ese JavaScript no
lograba engancharse a tiempo, el navegador hacía un submit HTML nativo que
en ciertas condiciones terminaba en un 419 real, y a veces en el aviso
nativo "reenviar formulario" del navegador.

La reconstrucción actual **no tiene ningún JavaScript interceptando
submits** — son formularios HTML puros con `@csrf`. Si de todos modos ves
un 419:

1. Verifica que `SESSION_DRIVER=redis` y que Redis esté sano:
   `docker compose exec redis redis-cli ping` → `PONG`.
2. Verifica que no haya dos pestañas con sesiones distintas del mismo
   usuario (una puede invalidar el token de la otra si el driver de sesión
   regenera el token).
3. Revisa `SESSION_DOMAIN` (`.env`) — debe quedar vacío/`null` en local
   (`http://localhost:8090`) y coincidir con el dominio real en producción
   (`https://platform.codered.host`). No hardcodear ninguno de los dos: la
   config ya usa `env('SESSION_DOMAIN')`.
4. `RucBackupCsrfTest` reproduce el flujo real (`GET` → extraer token →
   `POST` en la misma sesión) y falla si vuelve a aparecer un 419.

### "El archivo no es un backup RUC válido"

El archivo no pasó `pg_restore --list`, o contiene objetos de una tabla
distinta a `ruc_records`. No es un problema de extensión — revisa
`storage/logs/laravel.log` para el detalle exacto que dio `pg_restore`.

### Restore falla

Si `pg_restore`/`psql` fallan durante la restauración real, `ruc_records`
queda **exactamente como estaba** (ver "Restauración atómica" arriba) — no
hace falta recuperar nada manualmente. El backup de seguridad creado justo
antes del intento queda disponible en el panel por si se necesita.

### pg_dump / pg_restore no encontrados

```bash
docker compose exec app which pg_dump pg_restore psql
docker compose exec app pg_dump --version
```

Deben apuntar a PostgreSQL 16.x (mismo major que `docker compose exec
postgres postgres --version`). Si faltan, `docker/php/Dockerfile` debe
instalar `postgresql16-client` — reconstruir con
`docker compose build app queue scheduler`.

---

## Design System

La vista (`resources/views/ruc/admin/backups/index.blade.php`) usa
exclusivamente componentes de `resources/views/components/ui/`, documentados
con ejemplos interactivos en `/admin/design-system` (sección
**"Feedback & Operations"**):

| Componente | Uso en este módulo |
|---|---|
| `<x-ui.confirm-dialog>` | Confirmar restaurar (`tone="warning"`) y eliminar (`tone="danger"`), modo `form="id-del-form"` — sin `window.confirm()`, sin Livewire. |
| `<x-ui.file-dropzone>` | Selección del archivo a importar (drag & drop nativo; el `<form>` sigue haciendo el submit, el componente no sube nada). |
| `<x-ui.button>` | Todos los botones de acción, con `:loading`/spinner integrado para evitar doble submit. |
| `<x-ui.progress indeterminate>` | Estado "en curso" del diálogo de confirmación mientras el POST síncrono está en vuelo (nunca un porcentaje inventado). |
| `<x-ui.badge>` | Estado de cada backup (`success`/`info`/`danger`/`neutral`/`warning`). |

No se implementó un panel de progreso por etapas en vivo (`process-steps`)
para el restore de RUC: el restore sigue siendo **síncrono** (una sola
petición HTTP bloqueante, por diseño — ver "Restauración atómica" arriba),
así que no hay forma honesta de reportar avance real etapa por etapa desde
el navegador durante esa única petición. `<x-ui.process-steps>` y
`<x-ui.operation-status>` sí están disponibles y documentados en el Design
System para módulos que corran como job/cola con progreso real reportado
por el backend.

## Tests

```bash
docker compose exec app php artisan test --filter=RucBackup
docker compose exec app php artisan test --filter=Multipart
docker compose exec app php artisan test tests/Unit/DesignSystemComponentsTest.php tests/Unit/DesignSystemAccessibilityTest.php
```

- `RucBackupCreateTest` — creación, validación de contenido, checksum, ruta relativa.
- `RucBackupImportTest` — subida de un solo archivo vía `enctype="multipart/form-data"` tradicional (no confundir con el import "multipart" de RUC Tools, que es un concepto distinto: un backup dividido en varias partes), rechazo de archivos inválidos/de otra tabla.
- `RucBackupDownloadTest` — descarga, autorización, archivo faltante.
- `RucBackupRestoreTest` — checksum, safety backup, atomicidad ante fallo, bloqueo de restores simultáneos.
- `RucBackupPageTest` — la página carga, no hay `@match` literal ni `confirm()` nativo, usa `<x-ui.confirm-dialog>`, formularios tradicionales.
- `RucBackupCsrfTest` — reproduce el flujo real de sesión y demuestra que no hay 419.
- `DesignSystemComponentsTest` / `DesignSystemAccessibilityTest` (`tests/Unit/`) — contrato y accesibilidad de `confirm-dialog`, `file-dropzone`, `progress`, `process-steps`.

### Backup multipart (RUC Tools)

- `MultipartManifestTest` — validación del manifest: formato, `backup_type`, límites configurables, path traversal, índices no consecutivos, `sha256` inválido.
- `MultipartUploadSessionTest` — creación de sesión, auth/permiso, endpoint de estado, aislamiento entre usuarios.
- `MultipartPartUploadTest` — parte válida, checksum/tamaño/nombre incorrectos (422), índice inválido, reintento idempotente, nombre en disco nunca controlado por el cliente.
- `MultipartCompleteTest` — flujo completo con un dump real (generado por `pg_dump`, dividido en memoria por el test — nunca 443 MB reales), ensamblado, limpieza de partes, copia del manifest, rechazo de dump de otra tabla.
- `MultipartResumeTest` — estado parcial para reanudar, no reenvía partes ya verificadas, completar lo que falta termina el backup.
- `MultipartCancelTest` — cancela y borra partes temporales, nunca borra un backup ya completado, rechaza cancelar la sesión de otro usuario.
- `MultipartCleanupTest` — sesiones expiradas se cancelan y limpian, sesiones vigentes/completadas nunca se tocan, comando `ruc:cleanup-backup-uploads`.
