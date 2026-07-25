# Sincronización de agencias Shalom

## Arquitectura

Laravel crea una ejecución en `agency_import_runs`, almacena el archivo en `storage/app/imports/shalom/{run_id}/` y despacha `SyncShalomAgenciesJob` a la cola `agency-imports`. El contenedor interno `shalom-extractor` ejecuta Node.js, Playwright y Chromium; no publica puertos al host.

## Flujo

1. Un usuario con permiso `agencies.import` carga Chosen.
2. El Job consulta el extractor y genera `agencies-processed.json`, `report.json` y `extractor.log` sanitizado.
3. Se crean elementos de vista previa: `create`, `update`, `rename`, `unchanged`, `conflict`, `missing` o `invalid`.
4. Los conflictos, inválidos y registros sin cambios no se seleccionan automáticamente.
5. La confirmación usa transacción, bloqueo de filas e idempotencia por `external_id` y `code`.
6. Los valores entrantes vacíos no reemplazan datos existentes.
7. Los cambios reales de nombre actualizan `old_name` y crean historial con `source=shalom_sync` e `import_run_id`.

## Chosen

Acepta HTML con `<li>`, arreglos JSON o texto por líneas. Usa el número inicial como `external_id`; `- AEREO` se guarda como aéreo, `- TERRESTRE` como terrestre y un registro sin sufijo se considera terrestre. Se decodifican entidades HTML y se ignoran encabezados `Destino:`.

## Variables

```env
SHALOM_EXTRACTOR_ENABLED=true
SHALOM_EXTRACTOR_URL=http://shalom-extractor:3000
SHALOM_EXTRACTOR_TIMEOUT=180
SHALOM_EXTRACTOR_MAX_FILE_MB=10
SHALOM_PAGE_TIMEOUT_MS=120000
```

## Operación

```bash
docker compose build
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan queue:restart
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint
docker compose exec shalom-extractor npm test
docker compose config
```

Interfaz: `/admin/agencies/import/shalom`.

## Solución de problemas

Compruebe `docker compose logs shalom-extractor`, conectividad desde `app` hacia `http://shalom-extractor:3000/health`, Redis y que `queue` escuche `agency-imports`. Un fallo durante confirmación revierte toda la transacción y deja la ejecución lista para revisión.
