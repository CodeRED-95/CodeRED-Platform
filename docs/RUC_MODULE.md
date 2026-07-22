# Módulo RUC

CodeRED Platform integra la consulta y la importación periódica del padrón reducido SUNAT. El TXT puede superar 18 millones de líneas y se procesa fuera de HTTP y Livewire.

## Arquitectura masiva

- `ruc_records` es la fuente interna y `ruc` tiene índice único.
- El operador coloca el TXT en `storage/app/private/ruc/incoming`.
- `RucIncomingFileScanner` detecta solo TXT mediante el disco Laravel configurado, sin leer su contenido.
- El worker de `ruc-imports` lee en streaming, normaliza ISO-8859-1/Windows-1252 y construye la dirección desde todos los campos posteriores al UBIGEO.
- El catálogo `ubigeos` se carga una vez por job; no existe una consulta SQL por contribuyente.
- Cada lote entra en `ruc_staging` mediante PostgreSQL COPY.
- Los checkpoints guardan línea y byte offset en la misma transacción que staging.
- El merge usa `ON CONFLICT (ruc) DO NOTHING`; nunca consulta ni actualiza RUC uno por uno.
- Al completar, el TXT puede moverse al directorio privado de archivo.

## Directorios

```dotenv
RUC_IMPORT_DISK=local
RUC_IMPORT_INCOMING_DIRECTORY=private/ruc/incoming
RUC_IMPORT_WORKING_DIRECTORY=private/ruc/working
RUC_IMPORT_ARCHIVE_DIRECTORY=private/ruc/archive
RUC_IMPORT_ERRORS_DIRECTORY=private/ruc/errors
RUC_IMPORT_QUEUE=ruc-imports
RUC_IMPORT_SYNC_HASH_MAX_MB=100
```

Con el disco `local` de Laravel 12, la ruta física resuelta dentro del contenedor es `/var/www/html/storage/app/private/ruc/incoming`; en el host normalmente es `~/CodeRED-Platform/storage/app/private/ruc/incoming`. El resolver evita crear accidentalmente `storage/app/private/private`.

## Operación

```bash
cp padron_reducido_ruc.txt storage/app/private/ruc/incoming/
php artisan ruc:scan
php artisan ruc:register-file private/ruc/incoming/padron_reducido_ruc.txt
php artisan ruc:import ID
php artisan ruc:status --id=ID
php artisan ruc:pause ID
php artisan ruc:resume ID
php artisan ruc:cancel ID
php artisan ruc:cleanup --dry-run
```

La misma operación está disponible en `/admin/ruc/importaciones`. El panel muestra TXT disponibles, progreso, nuevos, existentes, inválidos, direcciones construidas, ubigeos resueltos/desconocidos, velocidad, ETA y heartbeat. Los archivos de varios GB nunca se suben mediante Livewire.

## Formato SUNAT y geografía

Los índices 0–4 son RUC, razón social, estado, condición y UBIGEO. Todos los valores desde el índice 5 forman la dirección; se omiten vacíos, `-`, `--`, `NULL` y `N/A`. El separador final `|` es válido.

La tabla `ubigeos` se sincroniza desde Alanube con `php artisan ubigeos:sync` y dispone del snapshot offline `database/data/ubigeos_alanube.json`. El job precarga código, departamento, provincia y distrito una sola vez.

## Seguridad y recuperación

Solo Super Administrador recibe permisos `ruc.*`. El archivo permanece en almacenamiento privado. El checksum impide reanudar un archivo modificado. Una caída conserva staging y el último checkpoint confirmado; `ruc:resume` continúa desde el byte offset. Update.sh consulta `ruc:has-active` y no recrea `codered-queue` durante una importación activa.

## Validación y registro manual

En `/admin/ruc/importaciones`, **Validar** lee como máximo 64 KB (20 líneas), detecta encoding y delimitador, verifica cabecera y muestra una estimación sin importar datos. **Registrar** vuelve a validar, rechaza rutas fuera de `incoming` y duplicados por SHA-256. Hasta `RUC_IMPORT_SYNC_HASH_MAX_MB` el hash se calcula por streaming en la petición; archivos mayores quedan en estado **Preparando** y `PrepareRucImportJob` calcula la huella en la cola `ruc-imports`.
