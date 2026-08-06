# Sistema de Backup y Restauración RUC

Gestiona backups de la tabla `ruc_records` (18M+ registros) con almacenamiento local en tu PC.

## 🏗️ Arquitectura

```
[Backup] → [Compresión gzip] → [Almacenamiento Local] → [Historial]
                                       ↓
                          [Descarga/Carga en PC]
                                       ↓
                              [Restauración]
```

**Características:**
- ✅ Backup completo con compresión (gzip nivel 6)
- ✅ Paralelización (4 jobs en restauración)
- ✅ Almacenamiento local en `storage/app/backups/ruc/`
- ✅ Descarga de archivos a tu PC
- ✅ Carga de archivos desde tu PC
- ✅ Validación SHA-256
- ✅ Rotación automática (30 días default)
- ✅ Dry-run para restauraciones
- ✅ Auditoría completa

---

## 📦 Variables de entorno

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=codered
DB_USERNAME=codered
DB_PASSWORD=secret

# RUC Backup
RUC_BACKUP_RETENTION_DAYS=30    # Retención por defecto (días)
RUC_BACKUP_SCHEDULE="0 2 * * *" # 2 AM cada día
```

## 💾 Ubicación de Almacenamiento

Los backups se guardan en:
```
storage/app/backups/ruc/ruc_backup_2026-08-06-143000.sql.gz
```

**Accesibilidad:**
- ✅ Fácil acceso desde terminal
- ✅ Descargar desde admin panel
- ✅ Cargar desde admin panel o formulario
- ✅ Tamaño: 2.5-3.5 GB para 18M+ registros

---

## 🎯 Uso

### Crear backup (manual)

```bash
# Full backup
php artisan ruc:backup --type=full

# Con usuario específico
php artisan ruc:backup --type=full --user=1

# Output esperado
Iniciando backup RUC (tipo: full)...
✅ Backup completado exitosamente

┌──────────────────────┬────────────────────────────┐
│ Propiedad            │ Valor                      │
├──────────────────────┼────────────────────────────┤
│ Backup ID            │ 1                          │
│ Nombre               │ ruc_backup_2026-08-06-...  │
│ Registros            │ 18,284,929                 │
│ Tamaño               │ 2.5 GB                     │
│ Almacenamiento       │ s3                         │
│ Duración             │ 245 segundos               │
│ Checksum             │ a1b2c3d4e5f6g7h8...       │
└──────────────────────┴────────────────────────────┘
```

### Listar backups

```bash
# Todos los backups
php artisan ruc:backups-list

# Solo completados
php artisan ruc:backups-list --status=completed

# Output esperado
┌────┬───────────┬──────────────────────────┬──────┬──────────────┬────────┬───────────────┬──────────────┬──────────┐
│ ID │ Status    │ Nombre                   │ Tipo │ Registros    │ Tamaño │ Almacenamiento│ Creado       │ Duración │
├────┼───────────┼──────────────────────────┼──────┼──────────────┼────────┼───────────────┼──────────────┼──────────┤
│ 1  │ ✅ comple │ ruc_backup_2026-08-06-14 │ full │ 18,284,929   │ 2.5 GB │ s3            │ 2026-08-06 14│ 245s     │
│ 2  │ ✅ comple │ ruc_backup_2026-08-05-02 │ full │ 18,284,929   │ 2.5 GB │ s3            │ 2026-08-05 02│ 238s     │
│ 3  │ ❌ fallid │ ruc_backup_2026-08-04-02 │ full │ null         │ -      │ s3            │ 2026-08-04 02│ -        │
└────┴───────────┴──────────────────────────┴──────┴──────────────┴────────┴───────────────┴──────────────┴──────────┘

Total: 3 backups
✅ Completados: 2 | ❌ Fallidos: 1
```

### Restaurar desde backup

```bash
# Dry-run (simular sin hacer cambios)
php artisan ruc:restore 1 --dry-run

# Restaurar de verdad
php artisan ruc:restore 1

# Confirmación interactiva
⚠️  ADVERTENCIA: Esto va a restaurar los datos del backup #1
   Nombre: ruc_backup_2026-08-06-143000.sql.gz
   Registros: 18,284,929
   Tamaño: 2.5 GB
   Creado: 2026-08-06 14:30:00
   ⚡ MODO REAL (se harán cambios)

¿Deseas continuar? (yes/no) [no]: yes

Restaurando backup...
✅ Restauración completada exitosamente

┌─────────────────────────────────┬─────────────────────────┐
│ Propiedad                       │ Valor                   │
├─────────────────────────────────┼─────────────────────────┤
│ Backup ID                       │ 1                       │
│ Registros restaurados           │ 18,284,929              │
│ Duración                        │ 156 segundos            │
│ Modo                            │ Real (datos cambiados)  │
└─────────────────────────────────┴─────────────────────────┘
```

---

## ⏰ Backup automático

### Registrar en Kernel

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Backup diario a las 2 AM
    $schedule->command('ruc:backup --type=full')
        ->dailyAt('02:00')
        ->onOneServer()
        ->runInBackground();

    // Limpiar backups expirados diariamente
    $schedule->job(CleanupExpiredBackupsJob::class)
        ->dailyAt('03:00')
        ->onOneServer();
}
```

### Verificar scheduler

```bash
# Ver próximas ejecuciones
php artisan schedule:list

# Ejecutar scheduler en desarrollo
php artisan schedule:work

# En producción (crontab)
* * * * * /usr/bin/php /var/www/html/artisan schedule:run >> /dev/null 2>&1
```

---

## 🔍 Validación de Backups

### Verificar integridad

```bash
# La tabla ruc_backups almacena checksum SHA-256
SELECT id, name, checksum_sha256, total_records 
FROM ruc_backups 
WHERE status = 'completed'
ORDER BY created_at DESC
LIMIT 10;
```

### Campos en ruc_backups

```
id                  BIGINT - ID único
name                VARCHAR - ruc_backup_2026-08-06-143000.sql.gz
backup_type         VARCHAR - full, incremental, manual
total_records       BIGINT - Cantidad de registros (18M+)
file_size_bytes     BIGINT - Tamaño comprimido (~2.5 GB)
storage_path        VARCHAR - Ruta en S3 o local
storage_type        VARCHAR - s3, local
compression_type    VARCHAR - gzip, bzip2
status              VARCHAR - pending, completed, failed, deleted
started_at          TIMESTAMP
completed_at        TIMESTAMP
duration_seconds    INT - Tiempo que tomó
error_message       TEXT - Si falló
checksum_sha256     VARCHAR - Para validar integridad
retention_days      INT - 30 días default
expires_at          TIMESTAMP - Cuándo se elimina
created_by          FK users - Quién lo creó
```

---

## 📊 Estimaciones de Rendimiento

### Para 18M+ registros

| Métrica | Valor |
|---------|-------|
| Dump sin comprimir | ~8-12 GB |
| Dump comprimido (gzip-6) | ~2.5-3.5 GB |
| Tiempo backup | 3-5 minutos |
| Tiempo restauración | 2-3 minutos |
| Upload a S3 | 5-10 minutos (depende de ancho de banda) |
| Compresión lograda | 70-75% |

### Factores que afectan:

- **CPU**: Más cores = compresión más rápida
- **I/O**: Velocidad del disco afecta dump
- **Red**: Ancho de banda para S3
- **Índices**: Cantidad y tamaño

---

## ⚠️ Consideraciones

### Seguridad

- ✅ Backups comprimidos en S3 con encriptación
- ✅ Checksum SHA-256 para validación
- ✅ Auditoría de quién hace backup
- ✅ Versiones antiguas se eliminan automáticamente

### Recuperación

- ✅ Dry-run antes de restaurar (sin cambios)
- ✅ Safety backup automático antes de restaurar
- ✅ Single transaction para atomicidad
- ✅ Rollback posible si algo falla

### Pruebas recomendadas

```bash
# 1. Crear backup
php artisan ruc:backup --type=full

# 2. Verificar archivo creado
ls -lh storage/app/backups/ruc/

# 3. Hacer dry-run de restauración
php artisan ruc:restore <backup_id> --dry-run

# 4. Restaurar en entorno de staging
php artisan ruc:restore <backup_id>

# 5. Validar datos después de restaurar
SELECT COUNT(*) FROM ruc_records;

# 6. Descargar desde admin panel
# Ir a admin > Backups > Descargar
```

---

## 💻 Panel de Admin

Acceder a **Admin → Gestor de Backups** para:

1. **Descargar archivos** a tu PC
2. **Cargar archivos** desde tu PC
3. **Restaurar** backups
4. **Eliminar** backups antiguos
5. **Ver historial** con detalles

---

## 🛠️ Troubleshooting

| Problema | Solución |
|----------|----------|
| Backup lento | Verificar CPU, I/O, considerar hacer en off-peak |
| Restauración falla | Verificar checksum, intentar dry-run, revisar logs |
| Espacio en disco | Verificar `storage/app/backups/ruc/`, limpiar backups antiguos |
| Archivo no se descarga | Verificar permisos de carpeta `storage/app/backups/ruc/` |
| Carga de archivo lenta | Depende del tamaño (2.5-3.5 GB), usar ancho de banda suficiente |

---

## 📋 Checklist de implementación

- [x] Migración creada
- [x] Modelo RucBackup con relaciones
- [x] Servicio RucBackupService
- [x] Comandos Artisan (backup, restore, list)
- [x] Job de limpieza automática
- [x] Documentación
- [ ] Configurar scheduler en Kernel.php
- [ ] Crear S3 bucket y permisos
- [ ] Probar con datos reales (18M+)
- [ ] Revisar logs y monitoreo

---

## 📞 API para programadores

```php
use App\Modules\Ruc\Services\RucBackupService;

// Crear backup
$backup = (new RucBackupService())->backup('full', $user);
echo $backup->getFormattedSize(); // "2.5 GB"

// Restaurar
$result = (new RucBackupService())->restore($backup, $dryRun = false);
// ['success' => true, 'records_restored' => 18284929, 'duration_seconds' => 156]

// Limpiar expirados
(new RucBackupService())->cleanupExpiredBackups();
```

---

## 📞 Rutas API

```php
// Descargar backup
GET /api/backups/{id}/download

// Listar backups
GET /api/backups/list

// Cargar backup
POST /api/backups/upload
Content-Type: multipart/form-data
- backup_file: file (.sql.gz, max 5GB)
```

## 🔐 Seguridad

- ✅ Solo admin puede descargar/cargar/restaurar
- ✅ Validación de permisos en todos los endpoints
- ✅ Checksum SHA-256 para integridad
- ✅ Logs de auditoría de todas las operaciones
- ✅ Archivos con permisos restringidos (0600)

---

**Versión:** 2.0  
**Última actualización:** 2026-08-06  
**Soporta:** 18M+ registros en ruc_records  
**Almacenamiento:** Local (PC)  
**Compresión:** Gzip nivel 6  
**Tamaño típico:** 2.5-3.5 GB
