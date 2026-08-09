# Registro de Cambios

Todos los cambios notables en CodeRED Platform se documentan en este archivo.

El formato se basa en [Mantener un Changelog](https://keepachangelog.com/es-ES/1.0.0/) y este proyecto sigue [Versionado Semántico](https://semver.org/lang/es/).

---

## [3.0.0] - 2026-08-09

### ELIMINADO (BREAKING)

Se retira por completo el **sistema de importación RUC**. La administración del
padrón pasa a hacerse exclusivamente mediante **backup y restore** de
`ruc_records`, que queda como fuente permanente.

**Los datos NO se tocan:** `ruc_records`, `ruc_backups`, `ruc_backup_operations`
y los backups en disco se conservan intactos. La migración de limpieza no
ejecuta `TRUNCATE` ni `DELETE` sobre el padrón.

- **Interfaz** — se elimina la pantalla de importaciones RUC, sus componentes
  Livewire (`Imports`, `ImportManager`, `ImportMonitor`) y el enlace del menú,
  reemplazado por "Backups RUC". El dashboard deja de mostrar métricas de
  importación.
- **Rutas** — `admin.ruc.imports` y `admin.ruc.imports.errors`.
- **Comandos** — `ruc:scan`, `ruc:import`, `ruc:pause`, `ruc:resume`,
  `ruc:cancel`, `ruc:status`, `ruc:cleanup`, `ruc:has-active`,
  `ruc:import-status`, `ruc:cleanup-imports`.
- **Jobs** — `PrepareRucImportJob`, `ProcessRucImportJob`, `ProcessRucImportJobV3`.
- **Clases** — 4 modelos, 3 enums, 1 policy, 1 evento, 5 objetos de datos,
  14 servicios y 2 helpers de soporte exclusivos de importación.
- **Tablas** (migración `2026_08_09_000001_drop_ruc_import_tables`) —
  `ruc_imports`, `ruc_import_errors`, `ruc_import_events`,
  `ruc_import_duplicates`, `ruc_staging`. También la columna huérfana
  `ruc_records.ruc_import_id` (su clave foránea impedía el DROP; en PostgreSQL
  `DROP COLUMN` es metadata-only y no reescribe la tabla) y
  `ruc_statistics.total_imports`.
- **Cola** — conexión `ruc-imports` en `config/queue.php` y su cola en el worker
  general. `ruc-backups` y su worker dedicado se mantienen sin cambios.
- **Configuración** — bloque `ruc.import` completo y todas las variables
  `RUC_IMPORT_*` de `.env.example`, del instalador y de la documentación.
- **Permisos** — `ruc.import`, `ruc.import-history`, `ruc.delete-import-file`,
  `ruc.cancel-import`, `ruc.view-errors` salen del seeder. Las filas ya
  existentes en base de datos NO se borran.
- **Eventos de integración** — `ruc.import.started`, `ruc.import.progress` y
  `ruc.import.finished` salen del catálogo `EventType`. Ningún productor los
  emitía, pero cualquier workflow n8n suscrito debe actualizarse.
- **Documentación** — se eliminan `DEPLOYMENT_RUC_V3.md` y los cuatro
  `RUC_IMPORT_V3_*.md`. El ADR 0039 se conserva marcado como **superado**.

### CORREGIDO
- **`RestoreRucBackupJob` fallaba siempre al terminar.** `DB::statement('ANALYZE
  ruc_records')` se usaba sin importar el facade `Illuminate\Support\Facades\DB`,
  así que PHP resolvía `App\Modules\Ruc\Jobs\DB` y lanzaba un `Error` fatal
  **después** de restaurar los datos correctamente, dejando la operación marcada
  como `failed` pese a que el padrón sí se había restaurado. Bug preexistente,
  no introducido por esta limpieza.
- `RucStatisticsService` contaba `ruc_imports`, lo que habría roto todo restore
  al soltar la tabla.

### AGREGADO
- Enlace directo a **Backups RUC** en la navegación lateral (antes la pantalla
  de backups no era alcanzable desde el menú).
- `tests/Feature/Ruc/RucImportSystemRemovedTest.php` — 14 pruebas que fijan la
  eliminación (rutas, tablas, clases, configuración) y verifican que
  `ruc_records`, sus columnas y sus datos siguen intactos.

## [2.3.1] - 2026-08-09

### CORREGIDO
- **RUC Backup/Restore — `UrlGenerationException` en `/admin/ruc/backups`**
  - La vista construia la URL de polling llamando a
    `route('admin.ruc.backups.operations.status', ['operation' => ''])` y
    concatenaba el UUID en JavaScript. Laravel descarta los parametros con
    valor vacio, asi que `{operation}` quedaba sin resolver y la pagina
    respondia HTTP 500 — pero solo cuando existia una restauracion activa,
    porque el bloque vive dentro de `@if($activeRestoreOperation)`.
  - Ahora la URL se genera en servidor con la operacion concreta
    (route model binding por `uuid`) y se pasa ya resuelta a Alpine.
  - El polling se detiene en estado terminal, ante 404/410 y al desmontar el
    componente; ya no se recarga la pagina cuando el restore falla, para no
    ocultar el error.
  - El restore fallido/completado sigue visible al recargar mediante un panel
    estatico (`RucBackupOperation::latestFinishedRestore()`), sin polling.
  - `RucBackupOperation::toStatusPayload()` unifica la forma del estado entre
    el endpoint JSON y el render inicial: `backup_name` ya no aparece en
    blanco hasta el primer poll.

### AGREGADO
- `tests/Feature/Ruc/RucBackupRestoreStatusUiTest.php` — 10 pruebas de
  regresion: pagina sin operacion, restore `pending`, `running`, `completed`
  y `failed`, guard de la ruta sin parametro, paridad del payload, y
  verificacion de que recargar la pagina no crea ni reinicia operaciones.

## [2.2.0] - 2026-08-06

### ✨ AGREGADO
- **RUC Import System v3.0** — Arquitectura completamente rediseñada
  - Procesamiento streaming de archivos ilimitados con O(1) memoria constante
  - Event sourcing completo para auditoría en tabla `ruc_import_events`
  - Tracking de duplicados en tabla `ruc_import_duplicates`
  - Progreso en tiempo real mediante broadcasting (actualización <1s)
  - Rollback automático con reversión segura de transacciones
  - Checkpoints transaccionales para recuperación ante interrupciones
  - Validación granular por línea con reportes detallados
  - Estrategias merge configurable (Insert, Insert-Update, Replace)
  - Pausa/Reanudación de importaciones en progreso
  - Cancelación segura manteniendo integridad transaccional
  - 7 servicios modulares para orquestación completa
  - 2 componentes Livewire 3 para UI en tiempo real

- **Migraciones v3.0**
  - Tabla `ruc_import_events` con índices de rendimiento
  - Tabla `ruc_import_duplicates` con unique constraints
  - 20+ nuevas columnas en `ruc_imports` para tracking
  - 4 campos nuevos en `ruc_import_errors` para resolución

- **Endpoints API RUC v3.0**
  - `POST /admin/ruc/imports` — Crear importación
  - `GET /admin/ruc/imports` — Listar importaciones
  - `GET /admin/ruc/imports/{id}` — Obtener detalles
  - `GET /admin/ruc/imports/{id}/progress` — Progreso en tiempo real
  - `POST /admin/ruc/imports/{id}/pause` — Pausar
  - `POST /admin/ruc/imports/{id}/resume` — Reanudar
  - `POST /admin/ruc/imports/{id}/cancel` — Cancelar
  - `POST /admin/ruc/imports/{id}/rollback` — Revertir
  - `GET /admin/ruc/imports/{id}/errors/download` — Descargar errores

- **Documentación v3.0**
  - `RUC_IMPORT_V3_IMPLEMENTATION.md` — Guía exhaustiva (2,500+ palabras)
  - `RUC_IMPORT_V3_QUICK_START.md` — Configuración en 60 segundos
  - `RUC_IMPORT_V3_CHECKLIST.md` — Validación de despliegue
  - `DEPLOYMENT_RUC_V3.md` — Guía de actualización con 12 pasos
  - `RUC_IMPORT_NEW_ARCHITECTURE.md` — Diseño detallado
  - `RUC_IMPORT_AUDIT.md` — Auditoría de v2.0

- **Sistema de versionado automático**
  - Versionado semántico (major.minor.patch)
  - Detección automática por tipo de commit (feat, fix, BREAKING)
  - Script artisan `app:bump-version` para actualización manual
  - CHANGELOG.md consolidado

### 📊 MEJORAS DE RENDIMIENTO
- **Velocidad**: 10x más rápido (1K → 10K registros/segundo)
- **Memoria**: 4x menos pico (512MB → 128MB)
- **Escalabilidad**: Archivos ilimitados (probado hasta 10GB+)
- **E/S**: 3x menos operaciones de base de datos

### 🔒 SEGURIDAD
- Tokens de una sola vez con `lockForUpdate()` para prevenir condiciones de carrera
- Hash bcrypt para tokens OTP
- Encriptación AES-256-CBC para datos personales
- Transacciones atómicas para reversión segura
- Autorización basada en políticas granular

### 📚 DOCUMENTACIÓN
- README.md actualizado con sección RUC v3.0
- Consolidación de CHANGELOG.md maestro
- Reorganización de documentación en estructura clara
- Guía de actualización simplificada con `./update.sh`

### 🛠️ DEVOPS
- `update.sh` automatiza 12 pasos de despliegue
- Respaldo automático de `.env`
- Construcción selectiva de imágenes Docker
- Verificación automática de salud post-despliegue
- Lista de verificación pre-despliegue incluida

---

## [2.1.0] - 2026-08-05

### ✨ AGREGADO
- Registros de Auditoría de Solicitudes de Tokens API (`ruc_import_audit_logs`)
- Tabla de Validaciones OTP para validación de códigos
- Seguridad mejorada en solicitudes de tokens API
- Campos adicionales en `api_token_requests` para seguimiento de entrega segura

### 🔒 SEGURIDAD
- Auditoría completa de solicitudes de tokens
- Validación de OTP con encriptación
- Protección de contacto de entrega (enmascarado)

---

## [2.0.0] - 2026-08-01

### ✨ AGREGADO
- RUC Import System v2.0 (arquitectura anterior)
- Procesamiento por lotes con tabla de almacenamiento temporal
- Registro de eventos básico
- Soporte para múltiples estrategias de fusión

### ⚠️ NOTA
- **Deprecado**: Versión reemplazada por v3.0 en 2026-08-06
- Limitaciones: máximo 2GB, alto consumo de memoria, sin progreso en tiempo real

---

## [1.0.0] - 2026-07-01

### ✨ AGREGADO
- Lanzamiento inicial de CodeRED Platform
- Gestión de agencias Shalom
- APIs para DNI, RUC y Agencias
- Integración n8n con CodeRED Agent
- Solicitudes de tokens API con aprobación
- Extensión Chrome Buscador Shalom
- Panel de administración

---

## Notas de versiones anteriores

Para cambios específicos en módulos, ver:
- `docs/changelog/TOKEN_REQUESTS.md` — Solicitudes de tokens API
- `docs/changelog/RUC_V3.md` — RUC Import System v3.0 en detalle
- `docs/CHANGELOG.md` — Registro de cambios histórico (deprecado, usar este archivo)
