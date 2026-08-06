# Estructura de Documentación

Guía sobre cómo está organizada la documentación del proyecto y dónde encontrar información específica.

## 📑 Estructura de directorios

```
CodeRED-Platform/
├── README.md                          # Inicio rápido y visión general
├── CHANGELOG.md                       # Changelog maestro consolidado
│
├── docs/                              # Documentación general
│   ├── INSTALL.md                     # Guía de instalación
│   ├── ENVIRONMENT.md                 # Variables de entorno
│   ├── DOCKER.md                      # Docker y Docker Compose
│   ├── API.md                         # Guía general de APIs
│   ├── SECURITY.md                    # Seguridad y protecciones
│   ├── DEVELOPMENT.md                 # Guía de desarrollo
│   ├── ARCHITECTURE.md                # Arquitectura general
│   │
│   ├── api/                           # Documentación de APIs específicas
│   │   ├── dni.md                     # API DNI
│   │   ├── ruc.md                     # API RUC (consultas)
│   │   ├── agencies.md                # API Agencias
│   │   └── token-requests.md          # API Solicitudes de tokens
│   │
│   ├── agent/                         # Documentación de CodeRED Agent
│   │   ├── architecture.md            # Arquitectura y diseño
│   │   ├── installation.md            # Instalación y setup
│   │   ├── security.md                # Seguridad y encriptación
│   │   └── n8n-migration.md           # Migración desde n8n antiguo
│   │
│   ├── changelog/                     # Changelog de módulos específicos
│   │   ├── TOKEN_REQUESTS.md          # Cambios en solicitudes de tokens
│   │   ├── RUC_V3.md                  # Cambios detallados de RUC v3.0
│   │   └── SECURITY.md                # Cambios de seguridad
│   │
│   ├── guides/                        # Guías paso a paso
│   │   ├── deployment.md              # Guía de despliegue
│   │   ├── monitoring.md              # Monitoreo y logs
│   │   ├── troubleshooting.md         # Resolución de problemas
│   │   └── backup-restore.md          # Backup y restauración
│   │
│   └── integrations/                  # Documentación de integraciones
│       ├── n8n-workflows.md           # Workflows de n8n
│       ├── telegram.md                # Integración Telegram
│       └── protocol.md                # Protocolo de integración
│
├── docs-ruc/                          # Documentación específica de RUC v3.0
│   ├── IMPLEMENTATION.md              # Guía exhaustiva de implementación
│   ├── QUICK_START.md                 # Configuración en 60 segundos
│   ├── ARCHITECTURE.md                # Diseño técnico detallado
│   ├── API.md                         # Endpoints API v3.0
│   ├── DEPLOYMENT.md                  # Despliegue de v3.0
│   ├── CHECKLIST.md                   # Validación pre/post despliegue
│   ├── TROUBLESHOOTING.md             # Problemas comunes y soluciones
│   ├── PERFORMANCE.md                 # Benchmarks y optimización
│   └── AUDIT.md                       # Auditoría de v2.0 (deprecado)
│
├── docs-security/                     # Documentación de seguridad
│   ├── TOKENS.md                      # Gestión de tokens API
│   ├── ENCRYPTION.md                  # Encriptación de datos
│   ├── AUDIT_LOG.md                   # Logs de auditoría
│   └── COMPLIANCE.md                  # Cumplimiento normativo
│
└── docs-dev/                          # Documentación para desarrolladores
    ├── CONTRIBUTING.md                # Guía de contribución
    ├── TESTING.md                     # Testing y calidad
    ├── CODE_STANDARDS.md              # Estándares de código
    └── VERSIONING.md                  # Sistema de versionado
```

## 🔍 Cómo encontrar documentación

### Por tarea
| Necesito... | Ir a... |
|------------|---------|
| Instalar el proyecto | `docs/INSTALL.md` |
| Entender la arquitectura | `docs/ARCHITECTURE.md` |
| Configurar variables de entorno | `docs/ENVIRONMENT.md` |
| Usar APIs de DNI/RUC | `docs/api/dni.md` o `docs/api/ruc.md` |
| Desplegar cambios | `docs/guides/deployment.md` o `DEPLOYMENT_RUC_V3.md` |
| Hacer troubleshooting | `docs/guides/troubleshooting.md` |
| Usar RUC v3.0 | `docs-ruc/QUICK_START.md` |
| Implementar RUC v3.0 | `docs-ruc/IMPLEMENTATION.md` |
| Entender seguridad | `docs/SECURITY.md` o `docs-security/` |
| Contribuir código | `docs-dev/CONTRIBUTING.md` |

### Por rol
| Rol | Lectura esencial | Referencia |
|-----|------------------|-----------|
| **Administrador** | `docs/INSTALL.md`, `docs/guides/deployment.md`, `docs/guides/monitoring.md` | `docs/ENVIRONMENT.md` |
| **Desarrollador** | `docs/ARCHITECTURE.md`, `docs/DEVELOPMENT.md` | `docs/api/`, `docs-dev/` |
| **DevOps** | `docs/DOCKER.md`, `docs/guides/deployment.md` | `docs/guides/`, `DEPLOYMENT_RUC_V3.md` |
| **Integrador n8n** | `docs/agent/`, `docs/integrations/n8n-workflows.md` | `docs/integrations/` |
| **Security/Audit** | `docs/SECURITY.md`, `docs-security/` | `docs/changelog/SECURITY.md` |

## 📝 Convenciones de documentación

### Estructura de un archivo .md
```markdown
# Título principal

Descripción breve (1-2 párrafos)

## Tabla de contenidos (si es largo >3 secciones)

## Secciones temáticas

### Subsecciones

## Ejemplos

## Véase también
- Referencia a otro documento
```

### Links internos
```markdown
# Correcto
Ver [instalación](docs/INSTALL.md)
Más en [API RUC](docs/api/ruc.md)

# Incorrecto
Ver docs/INSTALL.md
Ver archivo INSTALL.md
```

### Código
```markdown
# Correcto - lenguaje especificado
\`\`\`bash
./update.sh
\`\`\`

# Correcto - ejemplo con descripción
\`\`\`php
// Crear importación RUC
RucImport::create([...])
\`\`\`

# Incorrecto - sin especificar
\`\`\`
Algún código
\`\`\`
```

## 🔄 Versionado de documentación

- Documentación de nuevas features → misma versión en CHANGELOG.md
- Cambios importantes en arquitectura → bump de minor version
- Fixes de documentación → sin cambio de versión
- BREAKING CHANGES → bump de major version

## 📚 Documentos maestros vs. específicos

### Maestros (visión de conjunto)
- `README.md` — Inicio
- `CHANGELOG.md` — Historial de cambios
- `docs/ARCHITECTURE.md` — Diseño general
- `docs/API.md` — Guía de APIs

### Específicos (detalles)
- `docs-ruc/` — Todo sobre RUC v3.0
- `docs-security/` — Seguridad en profundidad
- `docs/guides/` — Guías paso a paso
- `docs-dev/` — Desarrollo y testing

## 🚀 Próximos pasos

1. Migrar documentación antigua a estructura nueva
2. Crear índices de búsqueda (doc site)
3. Generar PDF de documentación
4. Implementar versionado automático de docs
