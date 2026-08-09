# Formato CodeRED RUC Backup (`.rucbackup`)

Formato de backup del padrón RUC: **un solo archivo** para el operador, con los
datos troceados en lotes por dentro.

## Estructura

```text
ruc_backup_2026-08-09_141557_8d6b81.rucbackup     ← contenedor ZIP64
├── manifest.json
└── chunks/
    ├── 000001.csv.zst      ← 500 000 filas, CSV comprimido con zstd
    ├── 000002.csv.zst
    └── ...
```

No existen archivos externos tipo `backup_part_001`: los lotes viven **dentro**
del `.rucbackup`. `unzip -l archivo.rucbackup` lista el contenido en cualquier
máquina, sin herramientas propias.

## Por qué ZIP64 y no `tar.zst` ni un gzip único

El directorio central del ZIP permite abrir el chunk *N* **sin leer los N-1
anteriores**. Eso es lo que hace barata la reanudación: si una restauración
murió en el lote 23 de 37, se abre el 24 directamente y se sigue.

Con `tar.zst` o un gzip único habría que descomprimir en secuencia todo lo
anterior solo para llegar al punto de corte, y el coste de reanudar crecería
cuanto más avanzada estuviera la operación — justo al revés de lo deseable.

ZIP64 además supera el límite de 4 GB del ZIP clásico (libzip lo activa solo
cuando hace falta) y PHP lo maneja de serie con la extensión `zip`.

Cada chunk se guarda con método **STORE**: ya viene comprimido con zstd, y
comprimirlo otra vez solo gastaría CPU.

## `manifest.json`

```json
{
  "format": "codered-ruc-backup",
  "format_version": 1,
  "created_at": "2026-08-09T14:15:57-05:00",
  "application_version": "3.1.0",
  "schema_version": "2026_08_09_000003_add_chunked_restore_checkpoints",
  "source_table": "ruc_records",
  "total_records": 1000000,
  "batch_size": 500000,
  "total_batches": 2,
  "columns": ["id", "ruc", "razon_social", "..."],
  "compression": "zstd",
  "compression_level": 3,
  "chunks": [
    {
      "number": 1,
      "filename": "chunks/000001.csv.zst",
      "records": 500000,
      "first_id": 1,
      "last_id": 500000,
      "uncompressed_size": 128613842,
      "compressed_size": 7341210,
      "sha256": "…"
    }
  ],
  "chunks_sha256": "…"
}
```

`columns` se compara con el esquema real antes de restaurar: si no coincide, la
restauración se rechaza en vez de desplazar valores entre columnas.
`chunks_sha256` (hash de los hashes, en orden) identifica el backup concreto e
impide reanudar una operación con un archivo distinto.

## Backup

```bash
php artisan ruc:backup                        # lote por defecto (500 000)
php artisan ruc:backup --batch-size=250000
php artisan ruc:backup --legacy               # genera el .dump antiguo
```

Cada lote se genera enteramente por tubería, sin que ninguna fila pase por PHP:

```
psql "\copy (SELECT … WHERE id > $ultimo ORDER BY id LIMIT $lote) TO STDOUT CSV"
  │
  └─► zstd -T0 -3  ─►  chunks/00000N.csv.zst
```

Se avanza con **paginación por clave** (`WHERE id > $ultimo`), nunca con
`OFFSET`: con `OFFSET` PostgreSQL recorre y descarta las filas anteriores, así
que el coste del lote *N* crecería con *N* y el backup se degradaría
cuadráticamente.

## Restore

```bash
php artisan ruc:restore 12                        # por ID de backup
php artisan ruc:restore /ruta/archivo.rucbackup   # por ruta
php artisan ruc:restore 12 --resume               # reanuda desde el checkpoint
php artisan ruc:restore 12 --force                # sin confirmación
```

```
manifest → validar → verificar checksums → crear ruc_records_next
  → [COPY lote 1 → checkpoint] → [COPY lote 2 → checkpoint] → …
  → verificar total → crear índices → ANALYZE → swap atómico
```

Cada chunk se carga también por tubería, sin extraerlo a disco:

```
ZipArchive::getStream()  ─►  zstd -d  ─►  psql "\copy ruc_records_next FROM STDIN CSV"
```

### La tabla activa nunca se toca

Los datos **jamás** se cargan sobre `ruc_records`. Se construye
`ruc_records_next` y solo cuando el dataset completo está verificado se
intercambian los nombres. Durante la carga —minutos u horas— `ruc_records`
sigue sirviendo consultas con normalidad.

Si cualquier lote falla, basta con no hacer el swap: la tabla activa no se ha
modificado en ningún momento. Es la garantía que fija
`test_failure_in_a_middle_batch_leaves_the_active_table_untouched`.

El swap es lo único que bloquea la tabla y dura milisegundos: `ALTER TABLE …
RENAME` solo actualiza el catálogo, no mueve datos.

```
ruc_records       →  ruc_records_old     (se conserva para rollback)
ruc_records_next  →  ruc_records
```

### Sin transacción gigante

Cada lote es su propia unidad. Envolver 18M filas en una sola transacción haría
crecer el WAL sin control y un fallo al 95 % tiraría horas de trabajo. Como se
carga en una tabla que nadie consulta, la atomicidad solo se necesita en el
swap final.

### Índices

La tabla de staging se carga **sin índices** y se construyen al final, de
golpe: cargar contra 9 índices multiplica el tiempo de `COPY`. Los nombres se
normalizan durante el swap consultando `pg_indexes`, no una lista fija, para
que añadir un índice en el futuro no rompa el intercambio en silencio.

## Reanudación

Tras cada lote confirmado se guarda un checkpoint en `ruc_backup_operations`:
`current_batch`, `completed_batches`, `records_processed`, `bytes_processed`,
`staging_table` y un `checkpoint` JSON con el hash del backup.

`--resume` **no** salta ningún lote a ciegas. Antes de continuar comprueba que:

1. `ruc_records_next` existe;
2. el `chunks_sha256` del checkpoint coincide con el del archivo (mismo backup);
3. el número de filas del staging coincide exactamente con lo que declara el
   checkpoint.

Si algo no cuadra, se aborta y se pide reiniciar sin `--resume`.

## Cancelación, rollback y estado

```bash
php artisan ruc:restore-manage --status            # tablas y última operación
php artisan ruc:restore-manage --cancel            # detiene tras el lote actual
php artisan ruc:restore-manage --discard-staging   # elimina ruc_records_next
php artisan ruc:restore-manage --rollback          # devuelve ruc_records_old al puesto activo
```

La cancelación se atiende **entre lotes**, nunca a mitad de un `COPY`, para no
dejar el staging con un lote a medias. `ruc_records` no se ve afectada en
ningún caso, y el staging se conserva por si se quiere reanudar.

## Benchmarks

Medido en el entorno Docker de desarrollo (PostgreSQL 16, `shared_buffers=1GB`),
tabla de 450 MB con 1 000 000 de filas reales de padrón.

### Backup

| Lote | Chunks | Tamaño | Ratio | Duración | Velocidad | RAM PHP |
|---|---|---|---|---|---|---|
| 250 000 | 4 | 14.32 MB | 17.51× | 11.6 s | 86 263 reg/s | 36.5 MB |
| 500 000 | 2 | 14.34 MB | 17.49× | 12.4 s | 80 429 reg/s | 36.5 MB |
| 1 000 000 | 1 | 14.34 MB | 17.49× | 9.7 s | 102 885 reg/s | 36.5 MB |

Con 100 000 filas: 4 chunks de 25 000, 1.02 MB, 16.56×, 2.1 s.

**La RAM de PHP es idéntica en los tres casos** — 36.5 MB — porque ninguna fila
pasa por PHP. El tamaño de lote no cambia el consumo de memoria; cambia la
granularidad del checkpoint.

### Restore (1 000 000 filas, 2 lotes de 500 000)

| Fase | Tiempo |
|---|---|
| `COPY` de los 2 lotes | ~9 s (107 262 reg/s pico) |
| `PRIMARY KEY` | 1.3 s |
| `UNIQUE (ruc)` | 1.7 s |
| 7 índices btree | ~8.5 s |
| **GIN trigram** (`razon_social`) | **18.0 s** |
| `ANALYZE` | 1.5 s |
| Swap atómico | < 0.1 s |
| **Total** | **46 s** |

El índice GIN de búsqueda por razón social es el 39 % del tiempo total. Es el
coste inevitable de tener búsqueda por texto; el `COPY` en sí es solo el 20 %.

### Proyección a 18M+

El `COPY` y la compresión escalan linealmente (memoria constante, keyset
pagination). Extrapolando: ~3.5 min de `COPY` y ~5-6 min de índices, dominados
por el GIN. Con lotes de 500 000 serían 37 chunks, de modo que una interrupción
cuesta como mucho rehacer un lote.

## Elección del tamaño de lote

`RUC_BACKUP_BATCH_SIZE=500000` es el valor recomendado:

- **No afecta a la RAM** (medido: 36.5 MB con cualquier valor).
- Con lotes **más pequeños** se pierde menos trabajo al reanudar, pero hay más
  arranques de proceso y más entradas en el ZIP.
- Con lotes **más grandes** el rendimiento mejora ligeramente (menos arranques),
  pero una interrupción cuesta rehacer más trabajo.
- 500 000 → ~7 MB comprimidos por chunk y 37 chunks para 18M filas: granularidad
  razonable sin penalización medible.

## Compatibilidad con `.dump`

Los backups antiguos en formato `custom` de `pg_dump` **se siguen leyendo y
restaurando**: `ruc:restore` detecta el formato por contenido (presencia de
`manifest.json` dentro del contenedor), nunca por la extensión, y deriva al
camino legacy cuando corresponde.

**No se generan `.dump` nuevos**: `ruc:backup` produce `.rucbackup`. El flag
`--legacy` sigue disponible para casos puntuales, pero el formato antiguo no es
troceado ni reanudable y no debe usarse como formato por defecto.

## Requisitos de infraestructura

| Requisito | Dónde |
|---|---|
| binario `zstd` | `docker/php/Dockerfile` (declarado explícitamente) |
| extensión PHP `zip` | `docker/php/Dockerfile` (`docker-php-ext-install zip`) |
| `psql` | `postgresql16-client` |
| extensión `pg_trgm` | migración `enable_postgres_search_extensions` |

`update.sh` comprueba `zstd` y la extensión `zip` en cada despliegue, y avisa si
encuentra un `ruc_records_next` o `ruc_records_old` de una operación anterior.
