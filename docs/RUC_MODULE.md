# Módulo RUC

CodeRED Platform integra de forma nativa la consulta del padrón reducido SUNAT. La implementación tomó como referencia funcional `CodeRED-95/api-ruc` en el commit `c48da6c2ef6b6c5a818d9d6c809bcce63a327a53`, sin ejecutar ese proyecto como servicio externo.

## Arquitectura

- `ruc_records` es la fuente interna para las consultas.
- Redis almacena resultados exactos durante el TTL configurado.
- `GET /api/v1/ruc/{ruc}` exige `ruc:consultar`.
- `GET /api/v1/ruc/buscar` exige `ruc:buscar` y limita búsqueda y paginación.
- Las importaciones TXT se guardan en almacenamiento privado y se procesan en la cola `ruc-imports`.
- La ruta guardada es relativa al disco local privado (`ruc-imports/<uuid>.txt`) y debe ser visible por `app` y `queue`.
- Una importación normal solo inserta RUC nuevos. No sobrescribe registros existentes.

## Importar un padrón

Desde el panel use **Empresas y RUC > Importaciones RUC**, o ejecute:

```bash
php artisan ruc:import /ruta/padron.txt
php artisan ruc:import-status
php artisan ruc:import-status --id=123
php artisan ruc:cleanup-imports --dry-run
php artisan ruc:recalculate-metrics
```

`--sync` está reservado para archivos controlados y diagnóstico. `--force` vuelve a procesar un archivo con el mismo SHA-256, pero tampoco sobrescribe contribuyentes existentes.

El lector trabaja en streaming, valida el encabezado, convierte la codificación configurada a UTF-8, persiste progreso y errores, y escribe por lotes. Los errores pueden descargarse como CSV desde el historial.

## Operación y seguridad

- El archivo fuente nunca es público.
- Solo Super Administrador recibe los permisos `ruc.*` por defecto.
- Los tokens DNI, agencias y RUC son independientes.
- Las consultas se auditan sin almacenar el token ni el RUC en texto plano.
- La cola debe escuchar `ruc-imports,default`; `docker-compose.yml` ya contiene ese orden.
- `REDIS_QUEUE_RETRY_AFTER` debe superar `RUC_IMPORT_TIMEOUT` (7500 y 7200 segundos por defecto) para impedir reentregas mientras un job sigue activo.
- Tras modificar el worker, recrear los servicios con `docker compose up -d --build`, reiniciar `queue` y ejecutar `php artisan queue:restart`.
- Antes de importar un padrón grande, pruebe una muestra y confirme espacio libre, Redis y el worker.

## Variables

Consulte `.env.example` para límites, TTL, cola, tamaño de lote, codificación y delimitador. Cambie estos valores mediante configuración de despliegue; no incluya padrones ni secretos en Git.

## Recuperación

Si un worker se interrumpe, el progreso y heartbeat permanecen en `ruc_imports`. Puede reintentar el job usando el UUID, o volver a importar con `--force`; el índice único y `ON CONFLICT DO NOTHING RETURNING` mantienen la operación idempotente. Una importación cancelada deja intactos los registros ya insertados.
