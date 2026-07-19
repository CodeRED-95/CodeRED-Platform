# API

## Convención de respuesta

Éxito:

```json
{
  "success": true,
  "data": {},
  "meta": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Mensaje entendible",
  "errors": {}
}
```

## Endpoints existentes

| Método | Ruta | Descripción | Permisos |
|---|---|---|---|
| `GET` | `/api/v1/health` | Estado general de la aplicación | Público |
| `GET` | `/api/v1/agencies` | Listado público de agencias activas | Público |
| `GET` | `/api/v1/agencies/search` | Búsqueda rápida pública | Público |
| `GET` | `/api/v1/agencies/version` | Versión global pública de agencias | Público |
| `GET` | `/api/v1/agencies/snapshot` | Snapshot compacto para extensión | Público |
| `GET` | `/api/v1/agencies/{code}` | Detalle por código | Público |

## `GET /api/v1/health`

### Descripción

Devuelve estado de la aplicación, conexión a PostgreSQL, conexión a Redis, versión y hora del servidor.

### Ejemplo curl

```bash
curl http://localhost:8090/api/v1/health
```

## `GET /api/v1/agencies`

### Descripción

Lista agencias activas para consumo público.

### Parámetros

| Parámetro | Tipo | Descripción |
|---|---|---|
| `page` | entero | Página |
| `per_page` | entero | Tamaño de página |
| `department` | string | Filtro por departamento |
| `province` | string | Filtro por provincia |
| `district` | string | Filtro por distrito |
| `status` | string | Filtro por estado |
| `updated_after` | fecha | Filtro por fecha |
| `version` | entero | Versión de datos |
| `search` | string | Búsqueda |

### Ejemplo curl

```bash
curl "http://localhost:8090/api/v1/agencies?search=chachapoyas"
```

## `GET /api/v1/agencies/search`

### Descripción

Búsqueda rápida por código, nombre, ubicación y dirección.

### Ejemplo curl

```bash
curl "http://localhost:8090/api/v1/agencies/search?q=tacna"
```

## `GET /api/v1/agencies/version`

### Descripción

Devuelve la versión global de agencias y métricas básicas.

### Ejemplo curl

```bash
curl http://localhost:8090/api/v1/agencies/version
```

## `GET /api/v1/agencies/snapshot`

### Descripción

Snapshot compacto para extensión o integraciones ligeras. Incluye agencias activas y referencia compacta de trasladadas.

### Ejemplo curl

```bash
curl -i http://localhost:8090/api/v1/agencies/snapshot
```

## `GET /api/v1/agencies/{code}`

### Descripción

Devuelve el detalle de una agencia por código.

### Ejemplo curl

```bash
curl http://localhost:8090/api/v1/agencies/SHA-000003
```

## Rutas administrativas

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/admin/agencies` | Panel administrativo de agencias |
| `GET` | `/admin/agencies/create` | Alta de agencia |
| `GET` | `/admin/agencies/{agency}` | Detalle administrativo |
| `GET` | `/admin/agencies/{agency}/edit` | Edición de agencia |
| `GET` | `/admin/agencies/import` | Importador de agencias |
| `POST` | `/admin/agencies/import/preview` | Vista previa de importación |
| `POST` | `/admin/agencies/{agency}/move` | Gestión de traslado |

## Errores

Los errores detallados en producción deben evitar exponer trazas.

PENDIENTE DE CONFIGURAR

## Identificadores de agencias

Los recursos de agencias exponen `internal_id` (PK técnica), `id` (ID externo nullable), `code`, `texto_chosen_terrestre` y `texto_chosen_aereo`. `texto_chosen` permanece temporalmente como deprecated y devuelve, por orden, terrestre, aéreo o null. El snapshot para la extensión usa `id` como ID externo y mantiene el mismo fallback heredado. Las rutas continúan enlazando por Code; no se modificó el contrato de URLs.

## Contrato JSON definitivo de agencia

```json
{
  "internal_id": 25,
  "id": 610,
  "code": "SHA-000610",
  "agencia": "Yarinacocha Av Universitaria",
  "departamento": "Ucayali",
  "provincia": "Coronel Portillo",
  "distrito": "Pucallpa Yarinacocha",
  "direccion": "av. universitaria mza a lote 6",
  "link_mapa": "https://www.google.com/maps/dir/?api=1&destination=-8.38,-74.56",
  "tamano": "Pequeña",
  "texto_chosen_terrestre": "610 - ... - TERRESTRE",
  "texto_chosen_aereo": null,
  "texto_chosen": "610 - ... - TERRESTRE"
}
```

Las claves inglesas preexistentes permanecen temporalmente para compatibilidad hacia atrás. `texto_chosen` está deprecated: terrestre, después aéreo y finalmente null. Las rutas siguen usando Code.
