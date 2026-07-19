# 0032. Sincronización incremental mediante changelog append-only

**Estado:** Aceptado

## Contexto

Los timestamps de agencies permiten detectar altas y cambios mientras la fila existe, pero no sobreviven a force delete. El historial de auditoría tiene FK con borrado en cascada, guarda datos administrativos y no ofrece una secuencia contractual estable. Una solución basada solo en agencias más tombstones obligaría a combinar dos órdenes y resolver empates temporales.

## Decisión

Crear agency_sync_changes sin FK a agencies. Cada evento posee una secuencia monotónica, operación upsert/delete, identificadores, versión de esquema, fecha y snapshot del contrato público para upserts. El observer lo registra dentro de la misma transacción que el modelo. Los cursores contienen versión, secuencia y schema version, codificados URL-safe y firmados con HMAC de APP_KEY.

La metadata y el catálogo usan la última secuencia como revisión eficiente. El ETag del catálogo incorpora además filtros, búsqueda, orden y paginación. Los cambios vencen según API_AGENCY_CHANGELOG_RETENTION_DAYS; un watermark persistente permite responder full_sync_required sin devolver deltas incompletos.

## Consecuencias

- Create, update, soft delete, restore, importaciones y acciones masivas quedan cubiertos por eventos Eloquent.
- Force delete conserva un evento mínimo después de desaparecer la fila.
- El snapshot público consume almacenamiento durante la retención, pero evita N+1 y mantiene un evento reproducible aunque la agencia cambie o desaparezca después.
- El cliente debe realizar metadata → full sync → incremental desde el cursor de snapshot.
- Cambiar el contrato de forma incompatible exige incrementar API_AGENCY_SCHEMA_VERSION.

## Referencias

- [Sincronización](../api/synchronization.md)
- [OpenAPI](../openapi.yaml)
- [API](../API.md)
