# RUC Tool - Estructura del Proyecto

## Árbol de directorios

```
ruc-tool/
│
├── bin/
│   └── ruc-tool                    # Ejecutable principal (CLI)
│
├── src/
│   ├── Commands/                   # Comandos CLI (Symfony Console)
│   │   ├── InitCommand.php         # Inicializar BD
│   │   ├── ImportCommand.php       # Importar archivos
│   │   ├── ValidateCommand.php     # Validar sin importar
│   │   ├── ExportCommand.php       # Exportar datos
│   │   ├── StatsCommand.php        # Ver estadísticas
│   │   ├── SearchCommand.php       # Buscar registros
│   │   ├── BackupCommand.php       # Crear backup
│   │   ├── RestoreCommand.php      # Restaurar backup
│   │   ├── CleanDuplicatesCommand.php  # Limpiar duplicados
│   │   └── ConfigCommand.php       # Administrar configuración
│   │
│   ├── Services/                   # Servicios (lógica de negocio)
│   │   ├── ImportService.php       # Importación + streaming + batch
│   │   ├── ValidationService.php   # Validación de registros
│   │   ├── BackupService.php       # Backup/restore (gzip)
│   │   └── DatabaseService.php     # Operaciones de BD (futuro)
│   │
│   ├── Models/                     # Modelos de datos
│   │   ├── RucRecord.php           # Registro RUC
│   │   ├── ImportBatch.php         # Lote de importación
│   │   └── DuplicateRecord.php     # Registro duplicado
│   │
│   ├── Database/                   # Capa de datos
│   │   ├── Connection.php          # Conexión PDO
│   │   └── Schema.php              # Migraciones/esquema
│   │
│   └── Helpers/                    # Utilidades
│       ├── Logger.php              # Logging (Monolog)
│       ├── ProgressBar.php         # Progress bar visual
│       └── ConfigManager.php       # Gestión de config
│
├── config/                         # Archivos de configuración
│   ├── database.php                # Config de BD
│   └── validation.php              # Reglas de validación
│
├── templates/                      # Plantillas
│   └── ruc-tool.json.example       # Ejemplo de config
│
├── examples/                       # Ejemplos
│   └── sample_ruc_data.csv         # Datos de ejemplo
│
├── tests/                          # Tests unitarios
│   ├── ValidationServiceTest.php   # Tests de validación
│   └── ...
│
├── composer.json                   # Dependencias PHP
├── phpunit.xml                     # Config de tests
├── Dockerfile                      # Imagen Docker
├── docker-compose.yml              # Orquestación Docker
├── install.sh                      # Script instalación (Linux/Mac)
├── install.bat                     # Script instalación (Windows)
├── .gitignore                      # Ignore de Git
├── .env.example                    # Variables de entorno
│
├── README.md                       # Documentación principal
├── DEVELOPMENT.md                  # Guía de desarrollo
├── CHANGELOG.md                    # Historial de cambios
└── STRUCTURE.md                    # Este archivo
```

## Flujo de datos

```
┌─────────────────────────────────────────────────────────────┐
│                     Usuario CLI                             │
│              (ruc-tool import file.csv)                     │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│          ImportCommand (Symfony Console)                    │
│  • Procesa argumentos y opciones                            │
│  • Llama al servicio de importación                        │
└──────────────────────┬──────────────────────────────────────┘
                       │
        ┌──────────────┴──────────────┐
        ▼                             ▼
┌────────────────────┐      ┌──────────────────────┐
│  BackupService     │      │  ImportService      │
│  • Crea backup     │      │  • Lee archivo      │
│  • Comprime gzip   │      │  • Streams de datos │
│  • Guarda BD       │      │  • Normaliza datos  │
└────────────────────┘      └──────────────┬───────┘
                                          │
                            ┌─────────────┴─────────────┐
                            ▼                           ▼
                     ┌──────────────┐      ┌─────────────────────┐
                     │ CSV Reader   │      │ XLSX Reader         │
                     │ (League CSV) │      │ (PHPOffice)         │
                     └──────────────┘      └─────────────────────┘
                            │                           │
                            └─────────────┬─────────────┘
                                          ▼
                          ┌────────────────────────────┐
                          │  ValidationService        │
                          │  • Valida formato RUC      │
                          │  • Verifica duplicados     │
                          │  • Valida campos           │
                          └──────────┬─────────────────┘
                                     │
                    ┌────────────────┴────────────────┐
                    ▼                                 ▼
            ┌──────────────────┐        ┌──────────────────────┐
            │  Válidos         │        │  Inválidos/Duplicados│
            └────────┬─────────┘        └──────────┬───────────┘
                     │                             │
                     ▼                             ▼
            ┌──────────────────┐        ┌──────────────────────┐
            │  Batch Insert    │        │  Registrar Errores   │
            │  (1000 reg/batch)│        │  en import_errors    │
            │  Transacciones   │        │                      │
            └────────┬─────────┘        └──────────────────────┘
                     │
                     ▼
         ┌────────────────────────┐
         │  Connection (PDO)      │
         │  • SQLite              │
         │  • PostgreSQL          │
         │  • MySQL               │
         └────────┬───────────────┘
                  │
                  ▼
         ┌─────────────────────────────┐
         │  Base de Datos              │
         │  • ruc_records              │
         │  • import_batches           │
         │  • import_errors            │
         │  • duplicate_records        │
         │  • backups                  │
         └─────────────────────────────┘
```

## Arquitectura de la aplicación

### Capas

```
┌─────────────────────────────────────────────┐
│         CLI Layer (Symfony Console)         │  <- Usuario
├─────────────────────────────────────────────┤
│   Commands (Import, Validate, Export, etc)  │
├─────────────────────────────────────────────┤
│   Services (Import, Validation, Backup)     │  <- Lógica
├─────────────────────────────────────────────┤
│   Models (RucRecord, ImportBatch, etc)      │
├─────────────────────────────────────────────┤
│   Database (Connection, Schema)             │  <- Acceso a datos
├─────────────────────────────────────────────┤
│   PDO / SQL                                 │
├─────────────────────────────────────────────┤
│   SQLite / PostgreSQL / MySQL               │  <- Persistencia
└─────────────────────────────────────────────┘
```

## Dependencias principales

```
composer.json
├── symfony/console v7.0          # CLI framework
├── symfony/yaml v7.0             # YAML parser
├── doctrine/dbal v4.0            # Database abstraction
├── vlucas/phpdotenv v5.6         # .env loader
├── monolog/monolog v3.0          # Logging
├── league/csv v9.14              # CSV parser
└── phpoffice/phpspreadsheet v2.0 # Excel reader
```

## Configuración

### Archivo de configuración (~/.ruc-tool/ruc-tool.json)

```json
{
  "database": {
    "driver": "sqlite|pgsql|mysql",
    "host": "localhost",
    "port": 5432,
    "database": "ruc_db",
    "username": "user",
    "password": "pass"
  },
  "backup_directory": "~/.ruc-tool/backups",
  "logs_directory": "~/.ruc-tool/logs",
  "workers": 4,
  "batch_size": 1000,
  "timeout": 3600
}
```

## Bases de datos

### Tablas principales

```
ruc_records (18M+)
├── id (PK)
├── ruc (UQ) ← Índice primario
├── razon_social (INDEX)
├── nombre_comercial
├── domicilio
├── ubigeo (INDEX)
├── estado (INDEX)
├── ... (15 campos más)
└── created_at, updated_at

import_batches
├── id (PK)
├── filename
├── total_records
├── valid_records
├── invalid_records
├── duplicate_records
├── status
├── started_at, completed_at

import_errors
├── id (PK)
├── import_batch_id (FK)
├── ruc
├── error_message
├── error_type
└── row_number

duplicate_records
├── id (PK)
├── ruc
├── source_filename
├── duplicate_count
└── first_occurrence

backups
├── id (PK)
├── filename (UQ)
├── file_size
├── record_count
└── created_at
```

## Flujo de importación detallado

```
1. Import file (CSV/XLSX)
   ↓
2. Lectura streaming (O(1) memoria)
   ↓
3. Normalización de campos
   ↓
4. Validación por registro
   ├── RUC válido (11 dígitos + checksum)
   ├── Razón social no vacía
   ├── Campos opcionales en formato correcto
   └── No duplicado en BD
   ↓
5. Batch processing (1000 registros)
   ├── Válidos → INSERT en transacción
   ├── Duplicados → Registrar en duplicate_records
   └── Errores → Registrar en import_errors
   ↓
6. Commit de transacción
   ↓
7. Reporte final
```

## Optimizaciones

### Memoria
- ✅ Streaming de archivos (no cargar todo en memoria)
- ✅ Generator para lectura de filas

### Velocidad
- ✅ Batch inserts (1000 registros por transacción)
- ✅ Índices en columnas frecuentes (ruc, razon_social, ubigeo)
- ✅ WAL mode en SQLite (PRAGMA)
- ✅ Prepared statements

### Almacenamiento
- ✅ Compresión gzip en backups (nivel 6)
- ✅ Índices optimizados

## Documentación

| Archivo | Propósito |
|---------|-----------|
| `README.md` | Guía de usuario + instalación |
| `DEVELOPMENT.md` | Guía para desarrolladores |
| `STRUCTURE.md` | Este archivo (arquitectura) |
| `CHANGELOG.md` | Historial de versiones |
| `/config` | Ejemplos de configuración |

---

**Versión**: 2.2.0  
**Última actualización**: 2026-08-06
