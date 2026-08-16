# Despliegue Seguro de CodeRED Platform

Este documento describe el proceso correcto y seguro para desplegar cambios en CodeRED Platform.

## Principios

1. **Migraciones atómicas**: Las migraciones se ejecutan una sola vez, no múltiples veces en paralelo
2. **Sin datos destructivos**: Nunca se usan `migrate:fresh`, `db:wipe` o `down -v`
3. **Entorno de producción primero**: Los cambios se prueban en desarrollo antes de producción
4. **Reversibilidad**: Todo cambio debe poder revertirse sin pérdida de datos

## Procedimiento de Despliegue

### 1. Despliegue Automatizado (RECOMENDADO)

```bash
cd /ruta/a/CodeRED-Platform
./update.sh
```

El script `update.sh` se encarga de:

- Verificar entorno y prerrequisitos
- Hacer backup de `.env`
- Actualizar repositorio (git pull)
- Construir imágenes Docker necesarias
- Levantar servicios
- Ejecutar migraciones (una sola vez)
- Limpiar cachés de aplicación
- Reiniciar workers de colas
- Verificar salud de servicios

### 2. Pasos Individuales (PARA DEPURACIÓN)

Si `./update.sh` falla o necesitas depuración:

```bash
# 1. Verificar cambios de código
git pull --ff-only

# 2. Construir imágenes si cambió código
docker compose build

# 3. Levantar servicios (sin borrar volúmenes)
docker compose up -d --remove-orphans

# 4. Esperar a que PostgreSQL esté healthy
docker compose exec -T postgres pg_isready -U codered

# 5. Ejecutar migraciones UNA SOLA VEZ
docker compose exec -T app php artisan migrate --force

# 6. Limpiar cachés
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache

# 7. Reiniciar workers
docker compose exec -T app php artisan queue:restart
```

## Puntos Críticos

### ❌ NUNCA Hagas Esto en Producción

```bash
docker compose down -v          # Destruye volúmenes (PÉRDIDA DE DATOS)
php artisan migrate:fresh       # Destruye y recrea (PÉRDIDA DE DATOS)
php artisan db:wipe             # Borra base de datos (PÉRDIDA DE DATOS)
docker compose exec app php artisan db:seed  # Seeders en prod (DATOS DUPLICADOS)
```

### ✅ SIEMPRE Usa Esto

```bash
./update.sh                      # Script automatizado
docker compose build             # Construye imágenes seleccionadas
docker compose up -d             # Levanta sin borrar volúmenes
php artisan migrate --force      # Ejecuta migraciones pendientes
```

## Arquitectura de Migraciones

Las migraciones se ejecutan exclusivamente desde `update.sh` en el paso 7.

**NO** se ejecutan automáticamente desde:
- `codered-app` (PHP-FPM)
- `codered-queue` (Queue worker)
- `codered-scheduler` (Scheduler)

Esto evita:
- Race conditions
- Violaciones de constraints
- Estados inconsistentes de base de datos
- Deadlocks

## Resolución de Problemas

### PostgreSQL no queda healthy

```bash
docker compose logs postgres
docker inspect codered-postgres --format '{{json .State.Health}}'
```

**Solución**: Espera más tiempo (PostgreSQL startup inicial toma ~30s) o revisa logs.

### Migraciones fallan con "Column does not exist"

**Causa**: Migraciones incompletas o esquema inconsistente.

**Solución**:
1. Revisa el error específico
2. No intentes hacer rollback manualmente
3. Contacta a equipo de desarrollo

### PHP-FPM workers mueren (SIGKILL)

**Causa anterior**: Migraciones simultáneas desde múltiples contenedores (CORREGIDO).

**Si sigue ocurriendo**:
```bash
docker compose logs -f app
docker stats
```

Revisa si hay:
- Falta de memoria
- CPU agotado
- Peticiones HTTP extremadamente largas

### Agent no conecta

**Síntoma**: El agent logs muestran "heartbeat.failed" constantemente.

**Causa probable**: PHP-FPM no respondiendo (502/504).

**Solución**:
```bash
curl -v http://localhost:8090/api/v1/integrations/n8n/heartbeat
```

Si recibe 502, PHP-FPM no está respondiendo. Revisa:
```bash
docker compose logs app
docker compose restart app
```

## Verificación Post-Despliegue

Después de desplegar, ejecuta:

```bash
# Ver estado de servicios
docker compose ps

# Verificar logs
docker compose logs --tail=50 app
docker compose logs --tail=50 queue
docker compose logs --tail=50 scheduler

# Test de API
curl http://localhost:8090/healthz

# Test de scheduler
docker compose exec -T app php artisan schedule:list
```

### Comandos Esperados en Scheduler

```
tokens:expire-pending-requests    Hourly    Marca como vencidas solicitudes pendientes
```

## Despliegue en CI/CD

Si tienes CI/CD, usa:

```bash
./update.sh
```

El script es idempotente y seguro para ejecutar múltiples veces.

## Soporte

Para errores específicos, consulta:
- `CLAUDE.md` - Arquitectura y convenciones del proyecto
- `docs/INSTALL.md` - Instalación limpia
- `docs/ARCHITECTURE.md` - Decisiones de arquitectura

---

**Última actualización**: 2026-08-07
**Versión segura**: 2.2.0+

## Workers de cola

Como paran `queue`, `queue-ruc-backups` y `scheduler`, por que llevan `init` y
`pcntl`, y de donde salen sus `stop_grace_period`: [docs/WORKERS.md](WORKERS.md).
