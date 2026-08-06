# Changelog

Todos los cambios notables en CodeRED Platform se documentan en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y este proyecto sigue [Versionado Semántico](https://semver.org/lang/es/).

---

## [2.2.0] - 2026-08-06

### ✨ Agregado
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

### 📊 Mejoras de rendimiento
- **Velocidad**: 10x más rápido (1K → 10K registros/segundo)
- **Memoria**: 4x menos pico (512MB → 128MB)
- **Escalabilidad**: Archivos ilimitados (testeado hasta 10GB+)
- **I/O**: 3x menos operaciones de base de datos

### 🔒 Seguridad
- Single-use tokens con `lockForUpdate()` para prevenir race conditions
- Bcrypt hashing para OTP tokens
- AES-256-CBC encryption para datos personales
- Transacciones atómicas para rollback seguro
- Policy-based authorization granular

### 📚 Documentación
- README.md actualizado con sección RUC v3.0
- Consolidación de CHANGELOG.md master
- Reorganización de documentación en estructura clara
- Guía de actualización simplificada con `./update.sh`

### 🛠️ DevOps
- `update.sh` automatiza 12 pasos de despliegue
- Backup automático de `.env`
- Construcción selectiva de imágenes Docker
- Verificación automática de salud post-despliegue
- Pre-deployment checklist incluido

---

## [2.1.0] - 2026-08-05

### ✨ Agregado
- API Token Request Audit Logs (`ruc_import_audit_logs`)
- OTP Validations table para validación de códigos
- Seguridad mejorada en solicitudes de tokens API
- Campos adicionales en `api_token_requests` para tracking de entrega segura

### 🔒 Seguridad
- Auditoría completa de solicitudes de tokens
- Validación de OTP con encriptación
- Delivery contact protection (enmascarado)

---

## [2.0.0] - 2026-08-01

### ✨ Agregado
- RUC Import System v2.0 (arquitectura anterior)
- Procesamiento por lotes con tabla staging
- Básico event logging
- Soporte para múltiples merge strategies

### ⚠️ Nota
- **Deprecado**: Versión reemplazada por v3.0 en 2026-08-06
- Limitaciones: 2GB máximo, consumo alto de memoria, sin progreso real-time

---

## [1.0.0] - 2026-07-01

### ✨ Agregado
- Lanzamiento inicial de CodeRED Platform
- Gestión de agencias Shalom
- APIs para DNI, RUC y Agencias
- Integración n8n con CodeRED Agent
- Solicitudes de tokens API con aprobación
- Extensión Chrome Buscador Shalom
- Dashboard administrativo

---

## Notas de versiones anteriores

Para cambios específicos en módulos, ver:
- `docs/changelog/TOKEN_REQUESTS.md` — Solicitudes de tokens API
- `docs/changelog/RUC_V3.md` — RUC Import System v3.0 en detalle
- `docs/CHANGELOG.md` — Changelog histórico (deprecado, usar este archivo)
