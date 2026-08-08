# RUC Tool v2.3.0

> ## ⚠️ IMPORTANT — LOCAL TOOL ONLY
>
> **RUC Tools es una herramienta administrativa local (offline, de
> desarrollo/operación).** NO forma parte del runtime ni del despliegue de
> CodeRED Platform.
>
> - **NO** se despliega en producción.
> - **NO** se copia dentro de `codered-app` ni de `/var/www/html`.
> - **NO** aparece en el `Dockerfile` ni en el `docker-compose.yml` principal
>   de CodeRED Platform.
> - **NO** la instala, construye ni ejecuta `update.sh`.
>
> Este paquete (`packages/ruc-tools`) vive en el repositorio porque está
> versionado junto al resto del proyecto, pero se usa **exclusivamente en tu
> máquina**, con su propio `docker-compose.yml` local (ver más abajo). Todo lo
> que produce (backups, partes, manifests) se prepara aquí y se transporta
> manualmente a donde corresponda — RUC Tools nunca sube nada ni se conecta a
> producción por su cuenta.

Herramienta CLI standalone para importar el **padrón reducido de RUC (SUNAT)** a una base de datos **PostgreSQL local**, con un esquema **idéntico** al que usa [CodeRED-Platform](https://github.com/CodeRED-95/CodeRED-Platform) en producción. Los backups que genera son restaurables directamente con `php artisan ruc:restore` en el servidor, y viceversa.

## Por qué existe

Importar el padrón completo (18M+ registros) a través de la app web de CodeRED-Platform requiere Redis, un worker de colas y el stack completo de Laravel corriendo. Esta herramienta hace lo mismo **localmente, sin límites de memoria y sin esas dependencias**, usando el mismo mecanismo de alto rendimiento que producción (COPY nativo de PostgreSQL a una tabla staging + merge deduplicado), para que puedas preparar la base de datos en tu PC y subir un backup ya listo.

## Compatibilidad con producción

Esta herramienta replica exactamente:

- **El parser del padrón** (`RucPadronParser`): delimitador `|`, encoding `ISO-8859-1`, 15 columnas.
- **El esquema de `ruc_records`**: mismas columnas, mismos tipos, mismo índice GIN trigram sobre `razon_social`.
- **El catálogo de `ubigeos`**: mismos 1,874 registros (fuente Alanube), misma resolución departamento/provincia/distrito.
- **El mecanismo de merge**: `COPY` a tabla `ruc_staging` (UNLOGGED) → `INSERT ... ON CONFLICT` deduplicado por RUC vía `ROW_NUMBER()`, igual que `RucCopyLoader` + `RucMergeService`.
- **El backup/restore**: `pg_dump --format=custom --table=ruc_records` / `pg_restore`, igual que `RucBackupCommand`/`RucRestoreCommand`.

Un backup creado aquí puede restaurarse en producción con `php artisan ruc:restore`, y un backup de producción puede restaurarse aquí con `ruc-tool restore`.

## Instalación (Docker)

Requiere Docker Desktop. Todo corre en contenedores — no necesitas instalar PHP ni PostgreSQL en tu máquina.

```powershell
cd ruc-tools
docker-compose up -d --build
```

Esto levanta:
- **`ruc-tool-postgres`**: PostgreSQL 16 (puerto local `5433`, para no chocar con el Postgres de CodeRED-Platform en `5432`), afinado para cargas masivas (`shared_buffers`, `work_mem`, `synchronous_commit=off`).
- **`ruc-tool-cli`**: PHP 8.3 con la herramienta, corriendo en segundo plano listo para recibir comandos.

### Inicializar el esquema

```powershell
docker exec ruc-tool-cli php bin/ruc-tool init --host=postgres --password=secret
```

Esto crea `ruc_records`, `ubigeos`, `ruc_staging` y siembra el catálogo de 1,874 ubigeos.

### Acceso cómodo desde PowerShell

Para no escribir `docker exec ruc-tool-cli php bin/ruc-tool` cada vez, agrega esta función a tu perfil de PowerShell (`$PROFILE`):

```powershell
function ruc-tool {
    docker exec -it ruc-tool-cli php bin/ruc-tool @args
}
```

Luego simplemente: `ruc-tool stats`, `ruc-tool import archivo.txt`, etc.

## Uso

### Importar el padrón

```powershell
ruc-tool import padron_reducido_ruc.txt
```

Por defecto usa `--encoding=ISO-8859-1 --delimiter=| --strategy=insert` (igual que producción). Opciones:

```powershell
ruc-tool import padron_reducido_ruc.txt \
  --encoding=ISO-8859-1 \
  --delimiter=| \
  --strategy=insert \      # insert = ignora RUCs existentes | update = los sobrescribe
  --batch-size=50000 \     # filas por lote COPY
  --skip-backup            # omite el backup de seguridad automático
```

El proceso:
1. Cuenta las líneas del archivo (`wc -l`) para mostrar progreso/ETA.
2. Lee línea por línea con streaming (O(1) memoria — soporta archivos de 10GB+).
3. Parsea cada línea con el mismo algoritmo que `RucPadronParser`.
4. Resuelve `departamento/provincia/distrito` desde el `ubigeo` en memoria (O(1) por registro).
5. Carga cada lote vía `COPY` nativo a `ruc_staging` (UNLOGGED, sin overhead de WAL).
6. Al final, hace un único `MERGE` deduplicado (por RUC, quedándose con la primera ocurrencia) hacia `ruc_records`.

### Validar sin importar

```powershell
ruc-tool validate padron_reducido_ruc.txt --save-report=errores.json
```

### Ver estadísticas

```powershell
ruc-tool stats
```

### Buscar

```powershell
ruc-tool search --ruc=20123456789
ruc-tool search --razon="EMPRESA DEMO"
ruc-tool search --departamento=LIMA --estado=ACTIVO
```

### Backup y restore

`ruc-tool backup` hace **UN SOLO** `pg_dump` consistente de `ruc_records` y
lo divide automáticamente en partes de tamaño fijo (90 MiB por defecto),
pensadas para transportarse fácilmente (USB, subida por partes, etc.). Nunca
hace varios `pg_dump` independientes ni divide por rangos de RUC/id — el
split es puramente binario, después de que el dump ya existe completo.

```powershell
ruc-tool backup
```

```
RUC Backup
==========

 Registros:
 18,316,242

 Creando PostgreSQL dump...
 OK

 Tamaño:
 442.9 MiB

 Validando dump...
 OK

 SHA-256:
 d8542da4c863229aa968373887e2456523c2e2be7e932039d73adf6e4a1b9182

 Dividiendo en partes de 90 MiB...

 Parte 1/5      90 MiB  OK
 Parte 2/5      90 MiB  OK
 Parte 3/5      90 MiB  OK
 Parte 4/5      90 MiB  OK
 Parte 5/5    82.9 MiB  OK

 Verificando partes...
 OK

 [OK] Backup preparado correctamente.
```

El resultado es una carpeta en `~/.ruc-tool/backups/`, no un único archivo:

```
~/.ruc-tool/backups/ruc_backup_2026-08-08-125938/
  ruc_backup_2026-08-08-125938.manifest.json
  ruc_backup_2026-08-08-125938.dump.part0001   (90 MiB)
  ruc_backup_2026-08-08-125938.dump.part0002   (90 MiB)
  ruc_backup_2026-08-08-125938.dump.part0003   (90 MiB)
  ruc_backup_2026-08-08-125938.dump.part0004   (90 MiB)
  ruc_backup_2026-08-08-125938.dump.part0005   (82.9 MiB, resto)
```

Eso es lo que se transporta: las partes + el `manifest.json`. El `.dump`
completo se genera solo como paso intermedio y se borra automáticamente al
terminar (usa `--keep-full` si lo quieres conservar también).

Opciones:

```powershell
ruc-tool backup --part-size=90     # tamaño de cada parte, en MiB (default: 90)
ruc-tool backup --keep-full        # conserva también el .dump completo sin dividir
```

**Verificar** un backup dividido (manifest válido, todas las partes
presentes, checksums, SHA-256 total reconstruido por streaming):

```powershell
ruc-tool backup:verify ruc_backup_2026-08-08-125938.manifest.json
```

**Reconstruir** el `.dump` completo a partir de las partes (streaming, nunca
carga todo en memoria; el resultado es byte-idéntico al original y pasa
`pg_restore --list`):

```powershell
ruc-tool backup:join ruc_backup_2026-08-08-125938.manifest.json
```

**Restaurar** acepta tres formas de backup — nuevo dividido (manifest),
`.dump` de un solo archivo, o legado `.sql.gz`:

```powershell
ruc-tool restore ruc_backup_2026-08-08-125938.manifest.json   # dividido: verifica, reconstruye, restaura, borra el temporal
ruc-tool restore ruc_backup_2026-08-07-041050.dump            # un solo archivo
ruc-tool restore ruc_backup_2026-08-07-041050.sql.gz           # legado (ver nota abajo)
```

El backup generado es compatible 1:1 con `php artisan ruc:restore` en
producción (`pg_restore --list` acepta ambos formatos sin importar la
extensión), y puedes copiar backups de producción aquí y restaurarlos con
`ruc-tool restore`.

#### Nota sobre `.sql.gz`

El nombre `.sql.gz` de los backups **antiguos** es legado y engañoso: el
contenido siempre fue (y sigue siendo) un dump en **formato custom de
`pg_dump`**, no un archivo gzip real. Los backups **nuevos** usan la
extensión `.dump`, que refleja correctamente el formato. Ambos se
reconocen **por contenido** (`pg_restore --list`), nunca por extensión ni
MIME type — puedes renombrar cualquiera de los dos y seguirá funcionando.

#### Por qué el dump conserva el schema (no `--data-only`)

`ruc-tool restore` usa `pg_restore --clean --if-exists --single-transaction`
para hacer DROP+CREATE+COPY de forma atómica, lo que **requiere** que el
dump traiga el schema completo (`CREATE TABLE`, índices, secuencia). Un
`--data-only` dejaría a `--clean` sin nada que dropear y, sin un `TRUNCATE`
explícito en su lugar, un segundo restore duplicaría claves. CodeRED
Platform en producción sí generó su backup como `--data-only` (las
migrations de Laravel son la fuente de verdad del schema ahí), pero su
restore también funciona igual de bien con un dump full-schema como el que
genera esta herramienta: valida y restaura solo por contenido
(`pg_restore --data-only` sobre el archivo, sin importar si el archivo
tiene schema o no). Por eso mantener schema aquí no rompe compatibilidad en
ningún sentido con producción, y sí evita reescribir el mecanismo de
restore de esta herramienta (que si necesita `--clean --if-exists`).

### Reconstruir geografía (automatización de ubigeo)

Si necesitas re-resolver `departamento/provincia/distrito` para registros ya existentes (por ejemplo tras actualizar el catálogo de ubigeos), equivalente a `php artisan ruc:rebuild-addresses`:

```powershell
ruc-tool ubigeo:rebuild --only-missing --dry-run   # simular
ruc-tool ubigeo:rebuild --only-missing             # aplicar
```

### Exportar

```powershell
ruc-tool export --format=csv --output=ruc_export.csv
ruc-tool export --format=json --estado=ACTIVO --limit=1000
```

## Estructura de `ruc_records`

Idéntica a la migración `2026_07_21_000018_create_ruc_module_tables.php` de CodeRED-Platform:

| Campo | Tipo | Descripción |
|---|---|---|
| `ruc` | VARCHAR(11) UNIQUE | Número de RUC |
| `razon_social` | TEXT | Razón social |
| `estado` | VARCHAR(60) | ACTIVO, SUSPENSIÓN TEMPORAL, etc. |
| `condicion` | VARCHAR(60) | HABIDO, NO HABIDO, etc. |
| `ubigeo` | VARCHAR(12) | Código de ubigeo (6 dígitos) |
| `tipo_via`, `nombre_via`, `numero`, `interior`, `lote`, `manzana`, `kilometro`, `codigo_zona`, `tipo_zona`, `departamento_direccion` | — | Desglose de dirección tal como viene del padrón |
| `departamento`, `provincia`, `distrito` | VARCHAR(120) | Resueltos desde `ubigeo` vía el catálogo `ubigeos` |
| `direccion` | TEXT | Dirección concatenada (igual a `RucAddressBuilder`) |

## Formato del archivo de entrada

`padron_reducido_ruc.txt` de SUNAT: 15 columnas separadas por `|`, típicamente en `ISO-8859-1`:

```
ruc|razon_social|estado|condicion|ubigeo|tipo_via|nombre_via|codigo_zona|tipo_zona|numero|interior|lote|departamento_direccion|manzana|kilometro
```

Un archivo de ejemplo (UTF-8, para pruebas rápidas) está en `examples/padron_reducido_ruc_sample.txt` — para importarlo usa `--encoding=UTF-8`.

## Rendimiento

- **Import**: decenas de miles de líneas/segundo (limitado por I/O de disco y red al contenedor Postgres, no por PHP — el trabajo pesado lo hace `COPY` nativo).
- **Memoria**: O(1) — streaming línea por línea, sin cargar el archivo completo.
- **Ubigeo**: catálogo completo (1,874 filas) cacheado en memoria una sola vez por ejecución.

## Comandos disponibles

```
init             Inicializar esquema + sembrar ubigeos
import           Importar padrón RUC (.txt)
validate         Validar un archivo sin importarlo
export           Exportar ruc_records a CSV/JSON
stats            Ver estadísticas
search           Buscar registros
backup           Backup vía pg_dump, dividido en partes + manifest.json
backup:verify    Verificar un backup dividido (manifest, partes, checksums)
backup:join      Reconstruir el .dump completo a partir de las partes
restore          Restore desde manifest.json, .dump o .sql.gz legado
ubigeo:rebuild   Re-resolver departamento/provincia/distrito
config           Ver/editar configuración
```

## Notas sobre paridad con producción

Durante el desarrollo se detectaron y corrigieron dos problemas en el mecanismo de backup/restore que también existen tal cual en `RucBackupService.php` de CodeRED-Platform (no se modificó ese repositorio, solo se documenta aquí):

1. **`pg_restore --single-transaction --jobs=4`**: estas dos opciones son mutuamente excluyentes en `pg_restore` real; el comando falla siempre que se combinan.
2. **`TRUNCATE` antes de `pg_restore`**: si el restore falla después del truncate, los datos previos se pierden sin posibilidad de rollback. Esta herramienta usa `pg_restore --clean --if-exists --single-transaction` en su lugar, que hace drop+recreate+load de forma atómica.

También es importante fijar la versión del cliente `pg_dump`/`pg_restore` a la misma versión mayor que el servidor Postgres (aquí, 16) — un cliente más nuevo (ej. 18, que es lo que instala `apk add postgresql-client` sin versión en Alpine) genera dumps en un formato que un servidor/cliente más viejo no puede leer.

## Troubleshooting

**"Connection refused" al conectar a Postgres**: dentro del contenedor, el host de Postgres es `postgres` (nombre del servicio en `docker-compose.yml`), no `localhost`. Usa `--host=postgres` en `init`.

**Ver logs**: `docker exec ruc-tool-cli tail -f /root/.ruc-tool/logs/ruc-tool.log`

**Reiniciar desde cero**: `docker-compose down -v && docker-compose up -d --build` (borra todos los volúmenes, incluida la base de datos).
