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

Consulte `.env.example` para límites, TTL, cola, tamaño de lote, codificación y delimitador. El valor recomendado es `RUC_IMPORT_ENCODING=ISO-8859-1`; `latin-1` se acepta solo como alias normalizado por compatibilidad. Actualice el `.env` de producción y reinicie la cola, sin incluir padrones ni secretos en Git.

## Recuperación

Si un worker se interrumpe, el progreso y heartbeat permanecen en `ruc_imports`. Puede reintentar el job usando el UUID, o volver a importar con `--force`; el índice único y `ON CONFLICT DO NOTHING RETURNING` mantienen la operación idempotente. Una importación cancelada deja intactos los registros ya insertados.
# Formato reducido SUNAT y UBIGEO

El importador acepta el TXT separado por `|` del padrón reducido SUNAT, incluida la columna vacía producida por el separador final. Los índices 0–4 son RUC, razón social, estado, condición y UBIGEO; todos los valores desde el índice 5 forman la dirección. Los marcadores vacíos, `-`, `--`, `NULL` y `N/A` no forman parte de la dirección.

La geografía se resuelve desde la tabla `ubigeos`, precargada una vez por job, y nunca desde las etiquetas ambiguas posteriores al UBIGEO. La fuente sincronizable es la [tabla pública de Alanube](https://developer.alanube.co/v1.0-PER/docs/ubigeo-table); la columna visual “Ciudad” se mapea a provincia y capital se conserva por separado.

El snapshot versionado `database/data/ubigeos_alanube.json` permite instalar sin red y es la única fuente utilizada por `UbigeoSeeder`. La descarga nunca ocurre durante `db:seed` ni al arrancar Docker.

Comandos:

- `php artisan ubigeos:sync --dry-run`: descarga y valida sin escribir.
- `php artisan ubigeos:sync`: descarga, valida controles y hace upsert.
- `php artisan ubigeos:sync --no-download`: restaura el snapshot local.
- `php artisan ruc:rebuild-addresses --dry-run` y `--only-missing`: reconstrucción segura de RUC existentes.

La sincronización exige un mínimo de 1,800 filas, unicidad, códigos de seis dígitos y los controles `010101`, `150137` y `150140`. Nunca trunca ni elimina registros ante una descarga incompleta.
