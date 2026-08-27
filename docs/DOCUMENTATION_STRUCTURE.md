# Estructura de la documentación

Mapa de dónde vive cada tipo de documentación en el repositorio. Refleja la
estructura **real** del proyecto.

## Directorios

```
CodeRED-Platform/
├── README.md              # Punto de entrada: qué es, instalación, Docker, índice
├── CHANGELOG.md           # Historial de versiones (fuente canónica)
├── AGENTS.md · CLAUDE.md  # Guía para asistentes de IA que trabajan el repo
│
├── docs/                  # Documentación detallada
│   ├── INSTALL.md              # Instalación
│   ├── DEVELOPMENT.md          # Guía de desarrollo
│   ├── DEPLOYMENT_SAFE.md      # Despliegue y actualización seguros
│   ├── DOCKER.md               # Docker y Docker Compose
│   ├── ENVIRONMENT.md          # Variables de entorno (incluye migración de dominio)
│   ├── ARCHITECTURE.md         # Arquitectura general
│   ├── DATABASE.md · SEEDERS.md · REDIS.md
│   ├── SECURITY.md · AUTHORIZATION.md · AUDIT.md
│   ├── API.md                  # Guía general de la API
│   ├── AGENCIES.md             # Módulo Agencias
│   ├── importacion-shalom.md   # Sincronización Shalom (extractor + cola)
│   ├── RUC_MODULE.md           # Módulo RUC (padrón, backup y restore)
│   ├── DNI_LEGACY_MIGRATION.md # Migración del proveedor DNI
│   ├── DNI_NAME_SEARCH.md      # DNI por nombres (módulo del panel + endpoint)
│   ├── TESTING.md · development-testing.md
│   ├── TROUBLESHOOTING.md · FAQ.md · ROADMAP.md
│   ├── DESIGN_SYSTEM.md · USERS.md · CONTRIBUTING.md
│   │
│   ├── api/            # Referencia por endpoint (authentication, agencies,
│   │                   #   ruc, dni, tokens, errors, synchronization, …)
│   ├── agent/          # CodeRED Agent (architecture, installation, security,
│   │                   #   pairing, n8n-migration, troubleshooting)
│   ├── integrations/   # n8n (connector, pairing, discovery, protocol, …)
│   ├── adr/            # Architecture Decision Records (0001–0039)
│   ├── audits/         # Auditorías técnicas fechadas
│   ├── postman/        # Colección y entorno Postman
│   └── superpowers/    # Planes y especificaciones de desarrollo
│
├── docs-dev/          # VERSIONING.md (sistema de versión, fuente única)
├── docs-ruc/          # PERFORMANCE.md · LIST_PERFORMANCE.md · BACKUP_FORMAT.md
│
├── app/Modules/*/     # README/notas específicas por módulo
│   ├── Ruc/BACKUP_SYSTEM.md
│   ├── Shalom/README.md
│   └── ShalomRecordar/README.md
│
└── packages/*/        # Documentación propia de cada paquete/extensión
    ├── codered-agent/README.md
    ├── n8n-nodes-codered/README.md
    ├── codered-chrome-extension/ (README.md · CHANGELOG.md)
    ├── shalom-recordar-extension/ (README.md · CHANGELOG.md)
    └── ruc-tools/ (README.md · DEVELOPMENT.md · STRUCTURE.md · CHANGELOG.md)
```

## Dónde buscar

| Necesito… | Empieza por |
|---|---|
| Visión general e instalación | `README.md`, `docs/INSTALL.md` |
| Actualizar en producción | `docs/DEPLOYMENT_SAFE.md`, `update.sh` |
| Variables de entorno | `docs/ENVIRONMENT.md` |
| Levantar/operar contenedores | `docs/DOCKER.md` |
| Entender la arquitectura | `docs/ARCHITECTURE.md`, `docs/adr/` |
| Consumir la API | `docs/API.md`, `docs/api/`, o `/docs` en la app |
| Padrón RUC y backups | `docs/RUC_MODULE.md`, `app/Modules/Ruc/BACKUP_SYSTEM.md` |
| Agencias y sincronización | `docs/AGENCIES.md`, `docs/importacion-shalom.md` |
| Seguridad y auditoría | `docs/SECURITY.md`, `docs/AUTHORIZATION.md`, `docs/AUDIT.md` |
| Integración n8n / Agent | `docs/agent/`, `docs/integrations/` |
| Versionado del proyecto | `docs-dev/VERSIONING.md` |
| Contribuir / probar | `docs/CONTRIBUTING.md`, `docs/TESTING.md` |

## Convenciones

- La **versión** del proyecto vive en `composer.json > extra.version` (fuente
  única). Consúltala con `./bin/version.sh`.
- El **CHANGELOG** raíz es la historia canónica; se actualiza con cada bump.
- Las **decisiones de diseño** se registran como ADR en `docs/adr/` y no se
  reescriben: se añaden nuevas o se marcan como reemplazadas.
