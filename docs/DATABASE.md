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
| `agency_imports` | Cabecera de importaciones |
| `agency_import_failures` | Fallos de importación |

## Migraciones

Las migraciones están ubicadas en `database/migrations`.

## Seeders

Seeder actual:

- `DatabaseSeeder`

## Factories

Factories actuales:

- `UserFactory`
- `AgencyFactory`

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

## Relaciones

| Relación | Descripción |
|---|---|
| `users` ↔ `roles` | Muchos a muchos |
| `roles` ↔ `permissions` | Muchos a muchos |
| `agencies.created_by` | Usuario creador |
| `agencies.updated_by` | Usuario actualizador |
| `agencies.moved_to_agency_id` | Agencia destino del traslado |

## Soft Deletes

La tabla `agencies` usa `softDeletes()`.

## Backups

- PostgreSQL: `pg_dump`
- Redis: `SAVE`
- Archivos del importador: `storage/app`

## Restauración

PENDIENTE DE CONFIGURAR
