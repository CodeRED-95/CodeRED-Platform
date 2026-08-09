# Módulo RUC

CodeRED Platform expone el padrón reducido SUNAT como base de consulta interna
y vía API. **Desde la v3.0.0 el módulo no importa archivos TXT**: el padrón se
administra exclusivamente mediante **backup y restore** de `ruc_records`.

```text
RUC
├── Registros
│   ├── listado
│   ├── búsqueda
│   └── API
└── Backups
    ├── crear
    ├── descargar
    ├── restaurar
    ├── progreso
    ├── historial
    └── eliminar
```

## `ruc_records`: fuente permanente

`ruc_records` es la fuente de verdad del padrón y **no se recrea desde archivos**.
Sus datos solo cambian mediante una restauración de backup.

- `ruc` tiene índice único.
- Índices de búsqueda sobre `ruc`, `razon_social`, `estado`, `condicion` y `ubigeo`.
- El catálogo `ubigeos` resuelve departamento/provincia/distrito.
- `ruc_statistics` mantiene el conteo total para que ni el dashboard ni el panel
  ejecuten `COUNT(*)` sobre 18M+ filas en cada carga.

Mantenimiento del padrón sobre datos ya existentes:

```bash
php artisan ruc:rebuild-addresses --only-missing   # reconstruye dirección/geografía
php artisan ruc:recalculate-metrics                # invalida y muestra métricas
```

## Administración: backup y restore

Toda la gestión de datos vive en `/admin/ruc/backups`. Ver
[`app/Modules/Ruc/BACKUP_SYSTEM.md`](../app/Modules/Ruc/BACKUP_SYSTEM.md) para el detalle.

**Crear backup** → procesamiento en segundo plano → progreso → `completed` →
descargar o restaurar.

**Restaurar** → se crea una `RucBackupOperation` → `RestoreRucBackupJob` corre en
la cola dedicada `ruc-backups` → progreso persistido en base de datos →
`completed` / `failed`.

El restore crea siempre un *safety backup* previo y lo valida antes de tocar
`ruc_records`. Nunca se ejecuta `pg_dump`/`psql` dentro del request HTTP: un
restore de millones de filas puede tardar horas y Cloudflare cortaría la
conexión (era la causa del error 524).

La UI consulta `GET /admin/ruc/backups/operations/{operation}/status` cada 2 s
mientras hay una operación `pending`/`running`, y deja de consultar en cuanto la
operación alcanza un estado terminal. Sin operación activa no hay polling.

### Cola dedicada

`ruc-backups` es una conexión de cola **separada** con `retry_after` de 90000 s,
porque `retry_after` es propiedad de la conexión y debe superar el timeout más
largo del job (24 h). Compartir conexión provocaría que Laravel re-entregara un
restore todavía en curso y dos procesos `psql` tocaran `ruc_records` a la vez.

## Configuración

```dotenv
RUC_ENABLED=true
RUC_CACHE_ENABLED=true
RUC_CACHE_TTL=3600
RUC_RATE_LIMIT_PER_MINUTE=60
RUC_SEARCH_RATE_LIMIT_PER_MINUTE=20
RUC_SEARCH_MIN_LENGTH=3
RUC_SEARCH_MAX_RESULTS=100
RUC_BACKUP_MAX_UPLOAD_MB=5000
RUC_BACKUP_QUEUE=ruc-backups
```

Las variables `RUC_IMPORT_*` y `RUC_STAGING_*` fueron eliminadas en la v3.0.0.

## API pública

| Endpoint | Ability | Descripción |
|---|---|---|
| `GET /api/v1/ruc/{ruc}` | `ruc:consultar` | Consulta un RUC exacto |
| `GET /api/v1/ruc/buscar` | `ruc:buscar` | Búsqueda por razón social |

Ver [`docs/api/ruc.md`](api/ruc.md).

## Permisos

| Permiso | Alcance |
|---|---|
| `ruc.view` | Ver el padrón en el panel |
| `ruc.test` | Probar la API RUC desde el panel |
| `ruc.backup.view` | Ver backups y el estado de operaciones |
| `ruc.backup.create` | Crear e importar archivos de backup |
| `ruc.backup.download` | Descargar backups |
| `ruc.backup.restore` | Restaurar un backup |
| `ruc.backup.delete` | Eliminar backups |

Los permisos de importación (`ruc.import`, `ruc.import-history`,
`ruc.delete-import-file`, `ruc.cancel-import`, `ruc.view-errors`) se retiraron
del seeder en la v3.0.0.

## Historia

El sistema de importación de padrones TXT (streaming, `ruc_staging`, event
sourcing en `ruc_import_events`, rollback) existió hasta la v2.3.1 y se eliminó
en la v3.0.0. La decisión original está documentada —como registro histórico
superado— en [`docs/adr/0039-ruc-import-background-processing.md`](adr/0039-ruc-import-background-processing.md).
