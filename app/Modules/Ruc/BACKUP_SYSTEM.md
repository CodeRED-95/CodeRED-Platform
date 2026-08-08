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
2. **Importar Backup** — selecciona un archivo `.dump` (o `.gz` legado) y
   lo sube. Se valida por contenido, se calcula SHA-256, se guarda. **No
   restaura automáticamente** — importar y restaurar son pasos separados.
3. **Descargar** — descarga el archivo tal cual.
4. **Restaurar** — reemplaza el contenido de `ruc_records` con el del
   backup. Antes de tocar nada, crea automáticamente un backup de seguridad
   del estado actual (ver abajo). Pide confirmación del navegador
   (`confirm()`) antes de enviar el formulario.
5. **Eliminar** — borra el registro y el archivo.

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
secuencias, datos, índices, constraints). Si aparece **cualquier objeto que
no sea `ruc_records` o `ruc_records_id_seq`**, el archivo se rechaza por
completo — así un dump de otra tabla (`users`, `agencies`, `ruc_backups`,
lo que sea), subido por un usuario con permiso de importar, nunca puede
usarse para tocar nada fuera de `ruc_records`.

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

## Tests

```bash
docker compose exec app php artisan test --filter=RucBackup
```

- `RucBackupCreateTest` — creación, validación de contenido, checksum, ruta relativa.
- `RucBackupImportTest` — subida multipart tradicional, rechazo de archivos inválidos/de otra tabla.
- `RucBackupDownloadTest` — descarga, autorización, archivo faltante.
- `RucBackupRestoreTest` — checksum, safety backup, atomicidad ante fallo.
- `RucBackupPageTest` — la página carga, no hay `@match` literal, usa formularios tradicionales.
- `RucBackupCsrfTest` — reproduce el flujo real de sesión y demuestra que no hay 419.
