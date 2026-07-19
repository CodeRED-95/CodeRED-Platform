# Módulo Agencies

## Resumen

El módulo `Agencies` administra agencias de Shalom con soporte para:

- listado administrativo
- vista pública
- importación desde GitHub Gist
- snapshot público
- agencias trasladadas
- centro de operaciones

## Modelo

Modelo principal:

- `App\Modules\Agencies\Models\Agency`

## Campos

Campos detectados en `agencies`:

| Campo | Descripción |
|---|---|
| `code` | Código único tipo `SHA-000003` |
| `name` | Nombre de la agencia |
| `short_name` | Nombre corto |
| `slug` | Slug único |
| `department` | Departamento |
| `province` | Provincia |
| `district` | Distrito |
| `address` | Dirección |
| `reference` | Referencia |
| `phone` | Teléfono |
| `secondary_phone` | Teléfono secundario |
| `email` | Correo |
| `schedule` | Horario |
| `latitude` | Latitud |
| `longitude` | Longitud |
| `services` | Servicios JSON |
| `observations` | Observaciones |
| `status` | Estado |
| `source` | Fuente |
| `source_reference` | Referencia original de la fuente |
| `source_text` | Texto original del Gist |
| `map_url` | URL de mapa |
| `size` | Tamaño normalizado |
| `is_operations_center` | Indica centro de operaciones |
| `has_moved` | Indica traslado |
| `moved_to_agency_id` | Agencia destino |
| `moved_to_address` | Dirección destino manual |
| `move_notice` | Aviso público de traslado |
| `moved_at` | Fecha de traslado |
| `data_version` | Versión pública de datos |
| `last_verified_at` | Última verificación |
| `created_by` | Usuario creador |
| `updated_by` | Usuario actualizador |
| `deleted_at` | Soft delete |

## Estados

| Estado | Etiqueta |
|---|---|
| `active` | Activa |
| `inactive` | Inactiva |
| `temporarily_closed` | Cerrada temporalmente |
| `under_review` | En revisión |
| `moved` | Trasladada |

## Centro de Operaciones

El campo `co` del Gist se transforma en `is_operations_center`.

En interfaz:

- `Sí`
- `No`

En listado y detalle se muestra la insignia:

- `Centro de Operaciones`

## Selector de Agencia Destino

Cuando un formulario necesita relacionar una agencia con otra y la lista puede ser grande, CodeRED usa un combobox buscable en lugar de un `<select>` nativo expandido.

Reglas:

- no usar `<select multiple>` para relaciones simples;
- no usar `<select size="...">` salvo listas intencionalmente múltiples;
- no duplicar un select nativo y otro componente personalizado para el mismo campo;
- mostrar código, nombre y ubicación;
- excluir la agencia actual, eliminadas y destinos no válidos desde servidor;
- mantener el campo cerrado por defecto y abrirlo solo bajo interacción.

## Agencias trasladadas

Una agencia trasladada:

- tiene `has_moved = true`
- tiene `status = moved`
- sale del listado operativo público
- continúa disponible por su código
- puede apuntar a una agencia destino o a una dirección manual

## Papelera

El listado administrativo permite consultar agencias activas, solo la papelera o
todos los registros.

- Eliminar aplica soft delete y requiere `agencies.delete`.
- Restaurar requiere `agencies.restore`.
- Eliminar definitivamente requiere `agencies.delete` y `agencies.restore`.
- Las agencias eliminadas no aparecen en la API, búsqueda pública ni relaciones de
  destino.
- La eliminación definitiva borra también el historial relacionado mediante la FK
  con `cascadeOnDelete`; el observer no intenta crear registros huérfanos.

## Relaciones

| Relación | Tipo |
|---|---|
| `createdBy` | `belongsTo(User::class)` |
| `updatedBy` | `belongsTo(User::class)` |
| `movedToAgency` | `belongsTo(Agency::class)` |
| `movedFromAgencies` | `hasMany(Agency::class)` |
| `changeLogs` | `hasMany(AgencyChangeLog::class)` |

## Permisos

Permisos del módulo:

- `agencies.view`
- `agencies.create`
- `agencies.update`
- `agencies.delete`
- `agencies.restore`
- `agencies.import`
- `agencies.export`
- `agencies.view_history`
- `agencies.manage_status`

## Autorización

- `AgencyPolicy` gobierna el acceso al módulo.
- `Gate::before` permite el acceso total al rol `super-admin`.
- El rol `admin` obtiene los permisos operativos del módulo a través de seeders idempotentes.
- Las rutas administrativas siguen protegidas por `auth` y autorización nativa de Laravel.

## CRUD manual

- Crear fuerza `source = manual` y no permite suplantar procedencia importada.
- Editar preserva `source`, `source_reference` y `source_text` existentes.
- Código, slug, correo y espacios se normalizan antes de validar unicidad.
- Un traslado exige agencia destino o dirección manual.
- Listado y búsqueda admiten ubicación, estado, tamaño, fuente, centro de operaciones, traslado y registros eliminados.
- El CRUD registra auditoría y mantiene `data_version` mediante el observer.

## Flujo de trabajo

1. Se importa o crea una agencia.
2. Se valida y normaliza.
3. Si se marca traslado, se usa la Action central.
4. Se registra auditoría.
5. Se incrementa `data_version`.
6. Se actualiza snapshot y API pública.

## Panel administrativo

### Rutas

- `/admin/agencies`
- `/admin/agencies/map`
- `/admin/agencies/create`
- `/admin/agencies/{agency}`
- `/admin/agencies/{agency}/edit`
- `/admin/agencies/import`

### Componentes Livewire

- `App\Livewire\Admin\Agencies\Index`
- `App\Livewire\Admin\Agencies\Map`
- `App\Livewire\Admin\Agencies\Form`
- `App\Livewire\Admin\Agencies\Show`
- `App\Livewire\Admin\Agencies\Import`

## Vista pública

- `/agencies`
- `/agencies/{code}`

## API pública

- `/api/v1/agencies`
- `/api/v1/agencies/search`
- `/api/v1/agencies/version`
- `/api/v1/agencies/snapshot`
- `/api/v1/agencies/{code}`

## Mapa administrativo

La ruta `/admin/agencies/map` proyecta las coordenadas existentes sin depender de servicios cartográficos, API keys ni librerías JavaScript externas.

- permite buscar y filtrar por estado y departamento;
- agrupa agencias geográficamente próximas;
- excluye del mapa los registros sin coordenadas y muestra su total;
- ofrece detalle administrativo y apertura explícita en Google Maps;
- limita la carga inicial a 1.000 puntos y avisa cuando conviene acotar los filtros;
- requiere el permiso existente `agencies.view`.

La representación geográfica es orientativa. Los enlaces a Google Maps se generan con las coordenadas persistidas y se abren con `noopener noreferrer`.

### Mapa integrado en ubicación

Las vistas de detalle administrativa y pública reutilizan `x-ui.map-preview` cuando la agencia tiene latitud y longitud válidas. El mapa utiliza OpenStreetMap, mantiene las coordenadas como fuente autoritativa y presenta un marcador CodeRED sin instalar librerías ni requerir Google Maps.
