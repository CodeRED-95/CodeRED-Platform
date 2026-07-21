# Importador

## Formatos soportados

- URL raw de GitHub Gist o GitHub
- JSON pegado
- Archivo JSON de hasta 5 MB

## Asistente

La importación administrativa exige cinco pasos:

1. seleccionar o ingresar el origen;
2. validar la fuente completa;
3. revisar estadísticas, duplicados y una muestra de hasta 20 filas;
4. confirmar estrategia y estado inicial;
5. revisar el resumen persistido.

No se puede invocar la importación sin completar validación y confirmación. Al
validar se guarda un snapshot JSON en `storage/app/private/imports/agencies/previews`.
La Action procesa exactamente ese archivo y nunca vuelve a descargar la URL.

Las estadísticas de válidos, inválidos, advertencias y duplicados se calculan sobre
todas las filas, aunque la interfaz solo muestre las primeras 20.

## Seguridad de URL

Solo se aceptan HTTPS y los hosts `gist.githubusercontent.com` y
`raw.githubusercontent.com`. La descarga aplica timeout y límite de 5 MB.

## GitHub Gist

Estructura esperada:

```json
{
  "id": 3,
  "agencia": "Chachapoyas Co Dos De Mayo",
  "departamento": "Amazonas",
  "provincia": "Chachapoyas",
  "distrito": "Chachapoyas",
  "direccion": "Jr. Dos de Mayo",
  "texto_chosen": "Texto original",
  "link_mapa": "https://www.google.com/maps/dir/?api=1&destination=-6.23,-77.86",
  "tamano": "Grande",
  "co": true
}
```

## Mapeo

| Gist | Interno |
|---|---|
| `id` | `source_reference` |
| `agencia` | `name` |
| `departamento` | `department` |
| `provincia` | `province` |
| `distrito` | `district` |
| `direccion` | `address` |
| `texto_chosen` | `source_text` |
| `link_mapa` | `map_url` y coordenadas |
| `tamano` | `size` |
| `co` | `is_operations_center` |

## Normalización

- El código se genera como `SHA-` más el `id` con seis dígitos.
- Se limpian espacios sin eliminar tildes, eñes ni caracteres válidos.
- Las coordenadas se extraen de `link_mapa` cuando son válidas.
- `co` acepta booleanos, `1`, `0` y sus equivalentes de texto.
- Un tamaño desconocido queda nulo y genera una advertencia.

## Duplicados

`AgencyDuplicateFinder` centraliza el mismo orden para preview y Action:

1. `source = github_gist` y `source_reference = id`;
2. `code`;
3. nombre y ubicación normalizados.

## Estrategias

- `ignore_existing`: omite existentes.
- `update_existing`: actualiza únicamente campos importables no vacíos.
- `create_only_new`: crea solo registros nuevos.
- `mark_conflicts`: registra duplicados como incidencias.

La reimportación nunca sobrescribe campos manuales de traslado.

## Persistencia y resultado

- La vista previa no escribe agencias en base de datos.
- La importación real se procesa mediante `ImportAgenciesAction`.
- Se acepta el array raíz legado y los respaldos oficiales con agencias en `data.agencies`. También se reconocen `agencies` y `agencias`; vista previa e importación usan el mismo lector y rechazan módulos o versiones futuras incompatibles.
- El esquema oficial soportado es `agency-backup` versión 1, generado por `CodeRED Platform`. `record_count` debe coincidir con la colección real.
- La restauración oficial ignora los IDs internos del origen. Busca coincidencias por `external_id`, `code` y `source_reference`, incluyendo eliminados lógicamente.
- La persistencia completa es transaccional. Los registros inválidos bloquean la confirmación; un error inesperado revierte toda la operación.
- `deleted_at` se restaura de forma explícita y las relaciones `moved_to_agency_id` se resuelven en una segunda fase mediante el mapa de IDs del respaldo.
- `services` acepta array o JSON serializado. Booleanos, coordenadas, estados, tamaños y valores nulos se normalizan antes de persistir.
- Solo usuarios autorizados con `agencies.import` pueden abrir o ejecutar el asistente; por la matriz actual corresponde al Super Administrador.
- Los errores se guardan en `agency_import_failures`.
- El estado final es `completed`, `completed_with_errors` o `failed`.
- El resumen conserva importadas, actualizadas, omitidas y fallidas.
- Las agencias nuevas usan `source = github_gist`, `has_moved = false` y el estado
  inicial confirmado en el asistente.

## Formato de identificadores 2026-07

```json
{
  "id": 610,
  "agencia": "Yarinacocha Av Universitaria",
  "texto_chosen_terrestre": "610 - UCAYALI - YARINACOCHA - TERRESTRE",
  "texto_chosen_aereo": null
}
```

Mapeo: `id` → `external_id`; los dos campos Chosen conservan su nombre. El formato antiguo `texto_chosen` sigue admitido: se clasifica solo cuando contiene inequívocamente TERRESTRE o AEREO/AÉREO. Un texto ambiguo permanece en `source_text` y genera advertencia. Los campos nuevos tienen prioridad y nunca son sobrescritos por el heredado.

`external_id`, Code y `(source, source_reference)` se comparan antes de actualizar. Si identifican filas distintas, la fila se rechaza como conflicto de identidad. Los ID repetidos dentro del archivo también fallan.
