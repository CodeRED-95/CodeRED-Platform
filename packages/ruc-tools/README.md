# RUC Tool v2.2.0

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

```powershell
ruc-tool backup                                    # pg_dump --format=custom
ruc-tool restore ruc_backup_2026-08-07-041050.sql.gz
```

El archivo generado en `~/.ruc-tool/backups/` es compatible 1:1 con `php artisan ruc:restore` en producción, y puedes copiar backups de producción aquí y restaurarlos con `ruc-tool restore`.

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
backup           Backup vía pg_dump --format=custom
restore          Restore vía pg_restore
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
