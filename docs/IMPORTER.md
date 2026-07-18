# Importador

## Formatos soportados

PENDIENTE DE CONFIGURAR

## GitHub Gist

Estructura real observada:

```json
{
  "id": 3,
  "agencia": "Chachapoyas Co Dos De Mayo",
  "departamento": "Amazonas ",
  "provincia": "Chachapoyas",
  "distrito": "Chachapoyas",
  "direccion": "jr. dos de mayo cdra. 15 s/n chachapoyas, referencia: junto a terminal de combis etsa",
  "texto_chosen": "3 - AMAZONAS - CHACHAPOYAS - CHACHAPOYAS - CHACHAPOYAS CO DOS DE MAYO - TERRESTRE",
  "link_mapa": "https://www.google.com/maps/dir/?api=1&destination=-6.238673290149498,-77.86800826533634",
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
| `link_mapa` | `map_url` |
| `tamano` | `size` |
| `co` | `is_operations_center` |

## Generación de código

Formato:

```php
$code = 'SHA-' . str_pad((string) $row['id'], 6, '0', STR_PAD_LEFT);
```

## Limpieza de texto

Se aplica limpieza de espacios repetidos y `trim` sin eliminar tildes, eñes ni caracteres válidos.

## Coordenadas

Se extraen desde `link_mapa` con expresión regular.

## Centro de Operaciones

Valores aceptados para `co`:

- `true`
- `false`
- `1`
- `0`
- `"true"`
- `"false"`
- `"1"`
- `"0"`

Si no puede interpretarse:

- se guarda `false`
- se registra advertencia

## Duplicados

Orden actual de detección:

1. `source = github_gist` y `source_reference = id`
2. `code`
3. coincidencia normalizada por nombre y ubicación

## Vista previa

La vista previa no escribe en base de datos.

## Reimportación

La reimportación desde el Gist no debe borrar campos de traslado manuales.

## Estado inicial

Para registros del Gist:

- `source = github_gist`
- `status = under_review`
- `has_moved = false`

