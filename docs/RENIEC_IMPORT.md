# Importación masiva RENIEC

## Arquitectura

Los padrones RENIEC no atraviesan HTTP ni Livewire. Se copian a `storage/app/reniec/incoming/`, se registran con checksum SHA-256 y se procesan en `reniec-imports` mediante `codered-reniec-queue`. El worker lee con `fgets`, normaliza el encoding una línea a la vez, carga `reniec_import_staging` con PostgreSQL COPY y ejecuta un único merge masivo hacia `dni_records`.

La staging puede ser UNLOGGED: reduce WAL, pero puede perderse tras una caída de PostgreSQL. El archivo y el checkpoint confirmado se conservan; antes de reanudar se validan tamaño y checksum. Cada checkpoint confirma staging y byte offset en la misma transacción.

## Preparación

```bash
cp padron_reniec.txt storage/app/reniec/incoming/
php artisan reniec:scan
php artisan reniec:register-file reniec/incoming/padron_reniec.txt
php artisan reniec:import ID
```

Formato v1: `DNI|NOMBRES|APELLIDO_PATERNO|APELLIDO_MATERNO|FECHA_NACIMIENTO|SEXO|UBIGEO|`. DNI es string de ocho dígitos; fecha ISO `YYYY-MM-DD`. Se aceptan UTF-8, ISO-8859-1, Windows-1252, BOM, CRLF, LF y delimitador final.

## Operación

- `reniec:status [--id=]`: progreso, línea, offset y heartbeat.
- `reniec:pause ID`: pausa cooperativa después del lote.
- `reniec:resume ID`: verifica hash/tamaño y continúa con `fseek`.
- `reniec:cancel ID`: cancela después de confirmar el lote actual.
- `reniec:cleanup [--dry-run]`: retención de historiales.
- `reniec:validate-file PATH`: valida ubicación, espacio, tamaño y hash.
- `reniec:analyze`: actualiza estadísticas PostgreSQL.

Estrategias: `insert_ignore` (predeterminada) y `upsert`. `replace_snapshot` no está habilitada porque requiere un swap y rollback operativo separado.

## Capacidad, recuperación y seguridad

Antes de registrar se exige espacio libre mínimo de cuatro veces el archivo; se recomienda cinco. Los errores se escriben en CSV privado con número, código, hash y extracto limitado. No se guarda la línea completa. Una caída conserva el archivo, staging confirmada, línea y byte offset. No se debe ejecutar RUC y RENIEC simultáneamente en servidores de 4 GB.

Despliegue:

```bash
docker compose build app reniec-queue
docker compose up -d app postgres redis nginx scheduler
docker compose up -d --no-deps reniec-queue
docker compose exec -T app php artisan migrate --force
```
