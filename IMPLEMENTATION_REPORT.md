# Informe de implementación — Agencias / Shalom

## Implementado y completado

- Estructura nueva de Agency ya presente en el proyecto y endurecida con precisión `numeric(15,12)`.
- Campos heredados conservados sin eliminación destructiva.
- Generación centralizada de `place` y `map_url`.
- `old_name` e historial completo mediante `agency_name_histories`.
- Contexto correcto de historial para cambios manuales y `shalom_sync`.
- API v1 con estructura nueva y aliases temporales de compatibilidad.
- Parser Chosen existente para HTML, JSON y texto.
- Job Redis de análisis con bloqueo `WithoutOverlapping`.
- Vista previa completa con filtros por acción, selección y conflictos desmarcados.
- Confirmación transaccional, idempotente y por lotes lógicos.
- Archivos por ejecución bajo `storage/app/imports/shalom/{run_id}/`.
- Descarga autorizada de JSON procesado y reporte.
- Extractor Node/Playwright separado, interno, con healthcheck y normalización.
- Cola Docker actualizada para escuchar `agency-imports`.
- Documentación de estructura y sincronización.

## Validaciones realizadas en este entorno

- `php -l` sobre todos los archivos PHP de `app`, migraciones, rutas y configuración: correcto.
- `node --check shalom-extractor/index.js`: correcto.
- `node --test shalom-extractor/test/*.test.js`: correcto.
- Parseo YAML de `docker-compose.yml`: correcto.
- Confirmado que `shalom-extractor` no publica puertos al host.

## No ejecutado por limitaciones del entorno

El ZIP no contiene `vendor/` ni `node_modules/`; este entorno tampoco dispone de Composer ni Docker. Por ello no fue posible ejecutar PHPUnit, Pint, migraciones reales PostgreSQL, rollback real ni construir Chromium. Deben ejecutarse los comandos siguientes en el servidor o PC de desarrollo.

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

## Revisión manual recomendada

- Confirmar con una respuesta real actual de Shalom los nombres exactos de sus propiedades; el extractor admite varios aliases comunes, pero la web externa puede cambiar.
- Revisar duplicados de `external_id`. La migración crea el índice único seguro únicamente si no encuentra duplicados.
- Probar la extensión Chrome con la respuesta API compatible antes de retirar aliases heredados.
