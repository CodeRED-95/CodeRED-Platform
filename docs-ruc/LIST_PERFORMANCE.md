# Rendimiento del listado del padrón RUC

Medido sobre **18 000 000 de filas reales** en el entorno Docker
(PostgreSQL 16, `shared_buffers=1GB`), con `EXPLAIN (ANALYZE, BUFFERS)`.

Tabla: 3.7 GB · índices: 3.7 GB.

## Resumen

| Consulta | Antes | Después |
|---|---:|---:|
| Primera página del listado | 1.51 ms | **0.57 ms** |
| Página profunda (cursor) | 0.93 ms | **0.15 ms** |
| Búsqueda por RUC exacto | 2.63 ms | **0.95 ms** |
| Detalle por id | 5.19 ms | **0.13 ms** |
| Filtro `provincia` | 97.0 ms | **9.8 ms** |
| Filtro `distrito` | 105.6 ms | **1.5 ms** |
| Filtro `ubigeo` | 22.6 ms | **1.3 ms** |
| `provincia` + `condicion` | **10 943 ms** | **0.31 ms** |
| `distrito` + `estado` | 1 131 ms | **2.46 ms** |
| `ubigeo` + `departamento` | 792 ms | **8.09 ms** |

Lo que **no** se ejecuta nunca desde el listado:

| Operación evitada | Coste medido |
|---|---:|
| `COUNT(*)` sobre `ruc_records` | 7 963 ms |
| Paginación con `OFFSET 17000000` | 9 436 ms |

## El problema real: filtro + `ORDER BY id`

La consulta del listado es siempre de esta forma:

```sql
SELECT <10 columnas> FROM ruc_records
WHERE <filtro> = ?
ORDER BY id
LIMIT 51
```

Un índice de **una sola columna** no sirve aquí. PostgreSQL puede usarlo para
localizar las filas, pero después tiene que ordenarlas por `id`, así que en la
práctica prefiere recorrer la clave primaria y descartar lo que no casa. Con
18M filas y un filtro selectivo eso significa recorrer millones de filas para
devolver 51 — de ahí los 10 943 ms de `provincia + condicion`.

Un índice **compuesto `(columna, id)`** sí sirve: dentro de cada valor las
filas ya salen ordenadas por `id`, así que el escaneo se detiene a las 51.

```
provincia + condicion    10 943 ms  ->  0.31 ms
```

## Índices

| Índice | Tamaño | Por qué |
|---|---:|---|
| `ruc_records_pkey` | 386 MB | Cursor pagination y detalle por id |
| `ruc_records_ruc_unique` | 541 MB | Búsqueda exacta por RUC (y unicidad) |
| `ruc_records_razon_social_trgm_index` | 960 MB | GIN trigram, búsqueda por razón social |
| `ruc_records_provincia_id_index` | 617 MB | Filtro + orden por id |
| `ruc_records_distrito_id_index` | 689 MB | Filtro + orden por id |
| `ruc_records_ubigeo_id_index` | 541 MB | Filtro + orden por id |

### Eliminados

Seis índices de una sola columna (`estado`, `condicion`, `departamento`,
`provincia`, `distrito`, `ubigeo`, ~681 MB en total):

- **`estado` (4 valores), `condicion` (3), `departamento` (8)**: ningún valor
  es lo bastante selectivo para que el planificador los elija — se comprobó
  `idx_scan = 0`. Sin ellos las consultas siguen en 0.3-4.8 ms, porque el
  recorrido de la clave primaria encuentra 51 coincidencias enseguida cuando
  el filtro es tan común. **No se sustituyen por nada.**
- **`provincia` (195), `distrito` (1874), `ubigeo` (1874)**: sustituidos por su
  versión compuesta con `id`.

### Coste

Los compuestos ocupan ~5× más que su equivalente de una columna: añadir `id`
hace único cada valor y PostgreSQL ya no puede deduplicar entradas repetidas.
El total de índices pasa de ~2.5 GB a ~3.7 GB. Es el precio de convertir un
peor caso de 11 segundos en uno de 10 milisegundos.

## `pg_trgm`: sí está justificado

La pregunta era si el índice GIN de 960 MB se paga solo. Medido con el mismo
término selectivo:

| | Tiempo |
|---|---:|
| Con índice GIN trigram | 810 – 1 226 ms |
| Forzando escaneo secuencial (sin índice) | **11 403 ms** |
| Término común (`'%CONSTRUCTORA%'`) | **2 ms** |

El caso habitual —un término que aparece en muchas filas— se resuelve en 2 ms
porque el `LIMIT 51` se satisface enseguida. El caso lento es el contrario: un
término **muy selectivo**, donde el escaneo GIN tiene que recorrer la
intersección completa de listas de posting para encontrar unas pocas filas. Aun
así es 10× más rápido que sin índice.

**Conclusión: se mantiene.**

## VACUUM tras una carga masiva (no basta ANALYZE)

Un índice GIN construido durante una carga masiva acumula entradas en su
*pending list*. Mientras no se vacía, cada búsqueda por razón social tiene que
recorrerla linealmente:

| | Tiempo |
|---|---:|
| Después de `ANALYZE` (solo) | 6 996 ms |
| Después de `VACUUM ANALYZE` | 1 148 ms |

Solo `VACUUM` vacía esa lista. Por eso `RucChunkedRestoreService` ejecuta
`VACUUM ANALYZE` sobre la tabla de staging antes del swap, cuando todavía no
atiende consultas y por tanto no bloquea a nadie. Coste sobre 18M: ~6 s.

## Total de registros sin `COUNT(*)`

La cabecera muestra "N registros en el padrón" leyendo `ruc_statistics`, no
contando. `RucStatisticsService` actualiza ese metadato al terminar cada
restauración, que es el único momento en que el padrón cambia. Se cachea 1 h
bajo la clave `ruc:records:count`.

Un `COUNT(*)` sobre 18M cuesta ~8 s (escaneo secuencial paralelo de 3.7 GB) y
no hay índice que lo evite.

## Columnas

El listado selecciona **10 de las 22 columnas** — exactamente las que se
pintan. El desglose de la dirección (`tipo_via`, `nombre_via`, `numero`,
`interior`, `lote`, `manzana`, `kilometro`, `departamento_direccion`,
`tipo_zona`, `codigo_zona`) se carga **solo al abrir el detalle**, al que se
llega pulsando el RUC en el listado.

## Pendiente conocido: `GET /api/v1/ruc/buscar`

El endpoint público de búsqueda **no** está optimizado y es hoy el punto más
lento del módulo:

```php
RucRecord::query()->whereRaw('razon_social ILIKE ?', ['%'.$term.'%'])
    ->orderBy('razon_social')->orderBy('ruc')->paginate($perPage);
```

Medido con un término común (`'%SERVICIOS%'`, 2.25M coincidencias):

| Parte | Tiempo |
|---|---:|
| `COUNT(*)` que introduce `paginate()` | 9 766 ms |
| Página ordenada por `razon_social` | 10 481 ms |
| **Total del request** | **~20 s** |

Son tres problemas a la vez: `paginate()` obliga a contar, `ORDER BY
razon_social` obliga a examinar todas las coincidencias antes de devolver 20, y
`SELECT *` arrastra las 22 columnas.

**No se ha cambiado** porque `total` y `last_page` forman parte del contrato
público documentado en `docs/openapi.yaml` y los consume la extensión de
Chrome. Arreglarlo requiere decidir entre acotar el conteo (mantiene la forma
de la respuesta, cambia su significado cuando hay muchos resultados) o pasar a
cursor (cambia la forma). Es una decisión de producto.
