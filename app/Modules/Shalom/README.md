# Módulo Shalom Recordar

Integración para recibir y almacenar registros de entregas fallidas desde la extensión Chrome **Shalom Recordar**.

## Descripción

La extensión Shalom Recordar captura confirmaciones de entrega (DNI, RUC, OS, clave de validación) en sysprovincia2.shalomcontrol.com cuando el sistema falla. Los datos se cifran localmente en Chrome y se sincronizan diariamente a CodeRED Platform a través de API.

**Responsabilidad de CodeRED:** Recibir, validar y almacenar en PostgreSQL como plaintext para auditoría y recuperación de entregas fallidas.

## Endpoints

### POST /api/v1/shalom/sync
Recibe registros de sincronización de Shalom Recordar.

**Request:**
```json
{
  "username": "user_extension_123",
  "records": [
    {
      "field": "DNI",
      "value": "12345678",
      "timestamp": "2026-08-06T14:30:00Z"
    },
    {
      "field": "OS",
      "value": "OS-9876543",
      "timestamp": "2026-08-06T14:30:00Z"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "batch_id": "550e8400-e29b-41d4-a716-446655440000",
  "record_count": 2
}
```

**Status codes:**
- `200 OK` — Registros almacenados exitosamente
- `422 Unprocessable Entity` — Validación fallida

## Campos válidos

| Field | Descripción | Ejemplo |
|-------|-------------|---------|
| `DNI` | Documento Nacional de Identidad | `12345678` |
| `CE` | Carnet de Extranjería | `000123456` |
| `RUC` | Registro Único de Contribuyente | `20123456789` |
| `OS` | Orden de Servicio | `OS-9876543` |
| `Clave` | Clave de validación | `ABC123XYZ` |

## Modelos

### ShalomDeliveryRecord
Almacena un registro individual de entrega.

```php
$record = ShalomDeliveryRecord::create([
    'username' => 'user123',
    'field' => 'DNI',
    'value' => '12345678',
    'timestamp' => now(),
    'sync_batch_id' => 'uuid',
]);
```

**Atributos:**
- `id` — Primary key
- `username` — Usuario de la extensión (índice)
- `field` — Tipo de documento (DNI, CE, RUC, OS, Clave)
- `value` — Valor del documento
- `timestamp` — Timestamp original de captura en Chrome
- `sync_batch_id` — ID único de la sincronización (agrupa registros)
- `user_id` — FK a usuario CodeRED (opcional)
- `created_at`, `updated_at` — Timestamps del sistema

## Actions

### RecibeShalomSyncAction
Lógica de negocio para recibir y guardar sincronizaciones.

```php
$batchId = (new RecibeShalomSyncAction())->execute($records, $username);
```

**Responsabilidades:**
1. Validar que cada registro tiene campos requeridos
2. Guardar en BD de forma transaccional
3. Registrar en logs para auditoría
4. Retornar ID de lote para agrupación

## Componentes Livewire

### DeliveryRecordSearch
Componente para que usuarios busquen sus registros Shalom.

```php
// Buscar registros de un usuario
$this->dispatch('search', username: 'user123');

// Con filtro por tipo de documento
$this->field_filter = 'DNI';
$this->dispatch('search');
```

## Jobs

### DeleteExpiredRecordsJob
Limpia registros más antiguos de 90 días (ejecutable con scheduler).

```php
// app/Console/Kernel.php
$schedule->job(DeleteExpiredRecordsJob::class)->daily();
```

## Autenticación

Actualmente **sin autenticación formal** (extensión personal). 

**Opciones futuras:**
- API Key de la extensión (guardada en env)
- Bearer token enviado en header Authorization
- HMAC-SHA256 del payload (similar a webhooks de GitHub)

## Rate Limiting

```
100 requests per minute por IP
```

## Búsqueda y Recuperación

Buscar registros por usuario y tipo de documento:

```php
// Por usuario
ShalomDeliveryRecord::where('username', 'user123')->get();

// Por usuario y tipo
ShalomDeliveryRecord::where('username', 'user123')
    ->where('field', 'DNI')
    ->latest('timestamp')
    ->limit(100)
    ->get();

// Por batch
ShalomDeliveryRecord::where('sync_batch_id', 'uuid')->get();
```

## Testing

Ejecutar tests:
```bash
php artisan test tests/Feature/Shalom/StoreShalomSyncTest.php
```

Tests incluidos:
- ✓ Almacenar registros correctamente
- ✓ Rechazar tipos de campo inválidos
- ✓ Rechazar campos faltantes
- ✓ Rechazar formato de timestamp incorrecto
- ✓ Rechazar >500 registros por sync
- ✓ Agrupar registros por batch ID
- ✓ Asignar batch IDs únicos por sync
- ✓ Aceptar todos los tipos de campo

## Logs y Auditoría

Todos los syncs se registran en logs:

```
INFO Shalom sync received {
  "username": "user123",
  "batch_id": "550e8400-e29b-41d4-a716-446655440000",
  "record_count": 2
}
```

## Notas

- **URL del servidor:** `https://platform.codered.lat` (configurar en extensión)
- **Endpoint:** `/api/v1/shalom/sync`
- **Método:** POST
- **Content-Type:** `application/json`
- **Formato de timestamp:** ISO 8601 con Z (`Y-m-d\TH:i:s\Z`)
- **Max registros por sync:** 500
- **Retención de datos:** 90 días (configurable)
