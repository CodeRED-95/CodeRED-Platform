# Disaster Recovery de CodeRED Platform

Guía operativa breve para recuperar la plataforma ante pérdida de contenedores, corrupción de datos o fallo del host, sin borrar datos reales.

## 1. Inventario de persistencia

### Crítico

- PostgreSQL (`pgdata`)
- `.env`
- `APP_KEY` y demás claves de cifrado persistentes
- Volumen `codered_n8n_data`
- Volumen `codered-agent-data`
- `storage/` con archivos cargados, logs y backups RUC

### Importante

- Configuración de Docker Compose
- `docker/php` y `docker/nginx` personalizados
- Logs persistidos en `storage/logs`
- Backups de agencias y RUC

### Regenerable

- Contenedores Docker
- Cachés de Laravel
- Redis como caché/sesión temporal
- Assets compilados
- Workers y scheduler

> No registrar secretos reales en esta guía. Los nombres de variables sí pueden documentarse.

## 2. Qué debe respaldarse

### PostgreSQL completo

Debe incluir al menos:

- usuarios
- roles y permisos
- agencias
- tokens y solicitudes de token
- sincronizaciones de Shalom Recordar
- configuración persistida en base de datos
- auditoría y activity logs
- integraciones
- datos de RUC

Un respaldo completo de PostgreSQL es el artefacto principal de recuperación de la aplicación.

### RUC

El padrón RUC se respalda y restaura por su propio flujo. No debe confundirse con el backup completo de PostgreSQL:

- backup PostgreSQL = recuperación total de la aplicación
- backup RUC = recuperación focalizada de `ruc_records`

## 3. Ubicación de la persistencia

- `pgdata` almacena PostgreSQL.
- `redisdata` almacena Redis.
- `codered_n8n_data` almacena workflows, credenciales cifradas y metadata de n8n.
- `codered-agent-data` almacena identidad y secretos cifrados del agent.
- `storage/` guarda logs, sesiones, cache de Laravel y archivos temporales.

## 4. Cómo respaldar

### PostgreSQL

```bash
docker compose exec postgres pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" > backup.sql
```

Para restaurar en un entorno aislado, usar una base temporal o un contenedor de prueba.

### `.env` y claves

- Guardar una copia segura del `.env`.
- Preservar `APP_KEY`.
- Preservar claves de cifrado de n8n y del agent.

### RUC

Seguir el flujo de `/admin/ruc/backups` o `php artisan ruc:backup`.

### n8n

Respaldar:

- volumen `codered_n8n_data`
- `.env`
- `N8N_ENCRYPTION_KEY`
- `N8N_DB_PASSWORD`
- `CODERED_AGENT_LOCAL_API_TOKEN`

## 5. Verificación del backup

Antes de confiar en un backup:

1. Verificar que el archivo exista.
2. Confirmar tamaño razonable.
3. Validar checksum si el formato lo incluye.
4. Probar restauración en un entorno temporal.
5. Comprobar tablas, conteos y secuencias.

## 6. Recuperación de PostgreSQL

1. Levantar PostgreSQL con el volumen intacto si existe.
2. Restaurar desde `pg_dump` en un entorno controlado.
3. Confirmar migraciones y acceso Laravel.
4. Verificar conteos básicos y secuencias.

Si el volumen se perdió, se requiere restaurar desde un backup externo del dump completo.

## 7. Recuperación de RUC

1. Verificar que el backup RUC existe.
2. Restaurar en la cola dedicada `ruc-backups`.
3. Confirmar estado de la operación en la interfaz administrativa.
4. Validar conteos y acceso a `ruc_records`.

## 8. Recuperación de `.env` y claves

Restaurar:

- `.env`
- `APP_KEY`
- `N8N_ENCRYPTION_KEY`
- `N8N_DB_PASSWORD`
- `CODERED_AGENT_ENCRYPTION_KEY`
- `CODERED_AGENT_LOCAL_API_TOKEN`

Si se pierde una clave de cifrado, los datos ya cifrados pueden quedar inaccesibles.

## 9. Recuperación de n8n

1. Restaurar volumen `codered_n8n_data`.
2. Restaurar `.env`.
3. Verificar que `N8N_ENCRYPTION_KEY` no cambió.
4. Levantar `codered-n8n`.
5. Confirmar que la UI arranca y que el agent reconecta.

## 10. Recuperación de storage

1. Restaurar `storage/` si existía backup.
2. Confirmar permisos.
3. Limpiar cachés si hace falta.
4. Revisar logs y cargas.

## 11. Levantar Docker tras recuperación

```bash
docker compose up -d
docker compose ps
docker compose logs --tail=100 app
docker compose logs --tail=100 nginx
docker compose logs --tail=100 postgres
```

Validar también queue, scheduler, Redis, n8n y agent.

## 12. Validación mínima post-recuperación

- `docker compose ps` muestra servicios healthy.
- Laravel responde en `APP_URL`.
- PostgreSQL acepta conexiones.
- Redis responde.
- El login funciona.
- RUC abre y lista datos.
- Shalom Recordar carga sincronizaciones.
- n8n y agent vuelven a enlazarse si estaban en uso.

## 13. RPO / RTO

- **RPO**: depende de la frecuencia real de backups; si solo existe backup manual, la pérdida potencial puede ser de horas o días.
- **RTO**: la recuperación es razonablemente rápida si se conservan volúmenes y claves; si se pierde PostgreSQL o claves de cifrado, la recuperación se vuelve más compleja y depende del backup externo.

## 14. Referencias

- `docs/DEPLOYMENT_SAFE.md`
- `docs/DOCKER.md`
- `docs/RUC_MODULE.md`
- `docs/ENVIRONMENT.md`

