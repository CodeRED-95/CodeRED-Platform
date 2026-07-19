# Base de datos

## Modelo de datos

Tablas principales detectadas en el proyecto actual:

| Tabla | Propósito |
|---|---|
| `users` | Usuarios de la aplicación |
| `roles` | Roles de acceso |
| `permissions` | Permisos granulares |
| `role_user` | Relación usuarios-roles |
| `permission_role` | Relación permisos-roles |
| `personal_access_tokens` | Tokens de Sanctum |
| `activity_logs` | Auditoría base |
| `application_settings` | Configuración de aplicación |
| `agencies` | Agencias |
| `agency_change_logs` | Historial de cambios de agencias |
| `agency_sync_changes` | Changelog append-only para sincronización de clientes API |
| `agency_sync_states` | Watermark de retención del changelog incremental |
| `agency_imports` | Cabecera de importaciones |
| `agency_import_failures` | Fallos de importación |

## Migraciones

Las migraciones están ubicadas en `database/migrations`.

## Seeders

Seeders actuales:

- `DatabaseSeeder`
- `RolesSeeder`
- `PermissionsSeeder`
- `SettingsSeeder`
- `AdminSeeder`
- `AgencySeeder`

## Factories

Factories actuales:

- `UserFactory`
- `AgencyFactory`

## Notas sobre `newFactory()`

En módulos Laravel, si una factory está centralizada en `database/factories`, el modelo debe declarar explícitamente `newFactory()` para evitar resoluciones incorrectas como:

```text
Database\Factories\Modules\Agencies\Models\AgencyFactory
```

La factory de `Agency` se resuelve de forma explícita hacia `Database\Factories\AgencyFactory`.

## Índices

Índices detectados para `agencies`:

- `code`
- `slug`
- `status`
- `department`
- `province`
- `district`
- `updated_at` + `data_version`
- `status` + `department` + `province` + `district`
- `source` + `source_reference`

### Índice único parcial para `source` y `source_reference`

La migración `2026_07_17_000009_adjust_agency_source_reference_index.php` corrige la restricción heredada `agencies_source_reference_unique` y la reemplaza por un índice único parcial sobre:

```sql
(source, source_reference)
```

cuando `source_reference IS NOT NULL`.

Esto permite:

- repetir un mismo `source_reference` entre fuentes distintas;
- mantener múltiples filas con `source_reference = NULL`;
- bloquear duplicados dentro de la misma fuente sin alterar datos silenciosamente.

Si antes de la migración existen duplicados, el proceso se detiene con un error explícito para que el conflicto se resuelva manualmente.

## Relaciones

| Relación | Descripción |
|---|---|
| `users` ↔ `roles` | Muchos a muchos |
| `roles` ↔ `permissions` | Muchos a muchos |
| `agencies.created_by` | Usuario creador |
| `agencies.updated_by` | Usuario actualizador |
| `activity_logs.user_id` | Responsable del evento de Usuario |
| `agency_change_logs.user_id` | Responsable del evento de Agencia |
| `agencies.moved_to_agency_id` | Agencia destino del traslado |

## Soft Deletes

Las tablas `agencies` y `users` usan `softDeletes()`.

## Auditoría

`activity_logs.changed_fields` conserva la lista de campos modificados. Los valores
anteriores y nuevos permanecen en JSON y excluyen credenciales y tokens.

## Backups

- PostgreSQL: `pg_dump`
- Redis: `SAVE`
- Archivos del importador: `storage/app`

## Restauración

PENDIENTE DE CONFIGURAR

## ID externo y textos Chosen

La migración `2026_07_19_055312_add_external_identifiers_to_agencies_table.php` añade `external_id bigint nullable`, con índice único parcial para no nulos, y dos columnas `text` nullable. La PK `id` no cambia. La migración rellena external_id desde referencias numéricas no duplicadas y clasifica `source_text` sin alterar su contenido. Los valores ambiguos permanecen únicamente en `source_text`. El rollback elimina las columnas nuevas y, por tanto, puede perder ediciones realizadas en ellas; no modifica los datos heredados.

## Sincronización incremental

`agency_sync_changes` no tiene clave foránea hacia `agencies`: debe conservar tombstones incluso después de `forceDelete`. Cada fila guarda secuencia, operación, identificadores mínimos, versión de esquema y un snapshot JSON para eventos `upsert`. `agency_sync_states.minimum_sequence` registra la mayor secuencia eliminada por retención, de modo que un cursor anterior responda `full_sync_required` en vez de producir un delta incompleto. La retención se aplica con `agencies:prune-sync-changes` y no sustituye la auditoría administrativa.
