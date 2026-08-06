# Despliegue - RUC Import System v3.0

**Fecha:** 2026-08-06  
**Versión:** 3.0.0  
**Status:** ✅ Production Ready

---

## 🚀 DESPLIEGUE RÁPIDO (Recomendado)

**Solo 2 comandos:**

```bash
# 1. Actualizar código
git pull origin main

# 2. Ejecutar despliegue automático
./update.sh
```

**Eso es todo.** El script `update.sh` maneja:
- ✅ Migraciones (incluyendo v3.0)
- ✅ Compilación de imágenes Docker
- ✅ Cachés
- ✅ Verificación de salud

---

## 📋 QUÉ HACE `update.sh` (12 PASOS)

```
[1/12] Verificando entorno
  └─ Git, Docker, docker-compose, .env

[2/12] Respaldando configuración
  └─ Crea .env.backup-TIMESTAMP

[3/12] Actualizando repositorio
  └─ git pull --ff-only

[4/12] Revisando variables nuevas
  └─ PostgreSQL, CodeRED Agent, n8n

[5/12] Construyendo imágenes
  └─ Solo si hay cambios en app/queue/scheduler/n8n

[6/12] Levantando servicios
  └─ docker compose up -d --remove-orphans

[7/12] Ejecutando migraciones (todas)
  └─ php artisan migrate --force

[8/12] Ejecutando migraciones v3.0 RUC Import ⭐ NUEVO
  └─ Crea tablas: ruc_import_events, ruc_import_duplicates
  └─ Agrega campos nuevos a ruc_imports
  └─ Configura índices

[9/12] Limpiando cachés
  └─ optimize:clear, config:cache, route:cache, view:cache

[10/12] Reboot queue
  └─ queue:restart para reiniciar workers

[11/12] Verificando salud
  └─ php artisan about, CodeRED Agent healthcheck

[12/12] Actualización completada ✅
```

---

## 🔑 CAMBIOS REALIZADOS PARA v3.0

### Código actualizado:
```
database/migrations/2026_08_06_000001_create_ruc_import_v3_tables.php
config/queue.php
update.sh (paso 8 nuevo)
```

### Tablas nuevas:
```
ruc_import_events           (event sourcing)
ruc_import_duplicates       (tracking de duplicados)
```

### Campos nuevos en `ruc_imports`:
```
valid_lines, skipped_lines, warning_lines
updated_records, duplicate_records, skipped_records
checkpoint_line, checkpoint_byte_offset, checkpoint_timestamp
merge_strategy, skip_duplicates, skip_unknown_ubigeo
rollback_requested_at, rollback_started_at, rollback_completed_at
status_message, memory_peak_mb, duration_seconds
lines_per_second, estimated_time_left
```

---

## ⚠️ ANTES DE EJECUTAR

### Pre-requisitos:
```bash
# 1. Verificar que estás en la rama correcta
git branch
# Debe mostrar: * main

# 2. Verificar que no hay cambios locales
git status
# Debe mostrar "nothing to commit"

# 3. Verificar que Docker está corriendo
docker ps
# Debe mostrar contenedores: codered-app, codered-postgres, etc

# 4. Backup de .env (el script lo hace automáticamente)
cp .env .env.backup-manual-$(date +%Y%m%d-%H%M%S)
```

---

## 🔄 EJECUCIÓN PASO A PASO

### Opción 1: RECOMENDADO (Automático)
```bash
cd /var/www/html
git pull origin main
./update.sh
```

**Espera:** ~5-10 minutos dependiendo de cambios

### Opción 2: Manual (Solo si sabes lo que haces)
```bash
# Pull
git pull origin main

# Migraciones
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan migrate --path=database/migrations/2026_08_06_000001_create_ruc_import_v3_tables.php --force

# Cache
docker compose exec -T app php artisan optimize:clear

# Queue restart
docker compose exec -T app php artisan queue:restart

# Verify
docker compose ps
docker compose exec -T app php artisan about
```

---

## ✅ VERIFICACIÓN POST-DESPLIEGUE

### 1️⃣ Verificar que las tablas se crearon
```bash
docker compose exec -T app php artisan db:show | grep ruc_import
```

**Esperado:** Debe mostrar `ruc_import_events`, `ruc_import_duplicates`

### 2️⃣ Verificar migraciones
```bash
docker compose exec -T app php artisan migrate:status | grep 2026_08
```

**Esperado:**
```
2026_08_06_000001_create_ruc_import_v3_tables .................... Ran
```

### 3️⃣ Verificar contenedores
```bash
docker compose ps
```

**Esperado:** Todos los contenedores deben estar `Up`

### 4️⃣ Verificar health
```bash
docker compose exec -T app php artisan about
```

**Esperado:** Información del proyecto sin errores

---

## 🔙 ROLLBACK (SI FALLA ALGO)

### Opción 1: Revertir con backup automático
```bash
# El script creó .env.backup-TIMESTAMP
cp .env.backup-TIMESTAMP .env

# Revertir código
git reset --hard HEAD~1
git pull origin main

# Revertir migraciones
docker compose exec -T app php artisan migrate:rollback
```

### Opción 2: Restaurar desde backup completo
```bash
# Si tienes backup de BD anterior
docker compose down -v
# Restaurar volumen o BD desde backup
```

---

## 📊 MONITOREO POST-DESPLIEGUE

### Ver logs de migración
```bash
docker compose logs -f codered-app | grep -i "migrate\|ruc"
```

### Ver logs de queue
```bash
docker compose logs -f codered-queue
```

### Ver logs de nginx
```bash
docker compose logs -f codered-nginx
```

### Verificar queue workers
```bash
docker compose exec -T app php artisan queue:failed
docker compose exec -T app php artisan queue:work ruc-imports --dry-run
```

---

## 🐛 TROUBLESHOOTING

| Problema | Solución |
|----------|----------|
| `column "total_lines" does not exist` | Migración ya fue ejecutada, ignora el error |
| `The [ruc-imports] queue connection has not been configured` | Config/queue.php fue actualizado, ejecuta `optimize:clear` |
| `Table ruc_import_events already exists` | Migración es idempotente, ignora si ya existe |
| Servicios no levantaron | Verifica `docker compose logs codered-app` |
| PostgreSQL no healthy | Espera 30s, luego `docker compose restart codered-postgres` |

---

## 📞 SOPORTE

**Documentación del RUC v3.0:**
- `RUC_IMPORT_V3_IMPLEMENTATION.md` - Guía completa
- `RUC_IMPORT_V3_QUICK_START.md` - Inicio rápido
- `RUC_IMPORT_V3_CHECKLIST.md` - Validación

**Comandos útiles:**
```bash
# Ver estado de migraciones
docker compose exec -T app php artisan migrate:status

# Limpiar caché
docker compose exec -T app php artisan optimize:clear

# Acceder a tinker
docker compose exec app php artisan tinker

# Ver logs
docker compose logs -f codered-app
```

---

**Status:** ✅ Ready to deploy  
**Última actualización:** 2026-08-06  
**Version:** v3.0.0

