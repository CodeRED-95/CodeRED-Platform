# Changelog

Todos los cambios notables de este proyecto están documentados en este archivo.

## [2.3.0] - 2026-08-08

### Added
- `ruc-tool backup` ahora divide automáticamente el dump en partes de
  tamaño fijo (90 MiB por defecto, `--part-size=N` configurable) más un
  `manifest.json` — sigue siendo UN SOLO `pg_dump` consistente, dividido
  binariamente después, nunca varios dumps independientes.
- `ruc-tool backup:verify <manifest>` — valida manifest, partes, checksums
  individuales y SHA-256 total reconstruido por streaming.
- `ruc-tool backup:join <manifest>` — reconstruye el `.dump` completo a
  partir de las partes (streaming, byte-idéntico al original).
- `ruc-tool restore <manifest.json>` — restaura directamente desde un
  backup dividido (verify → join a temporal → pg_restore → borra temporal).
- `--keep-full` en `backup` para conservar también el `.dump` sin dividir.
- Validación de contenido (`pg_restore --list`) antes de dividir o
  restaurar: rechaza dumps corruptos o que no pertenezcan a `ruc_records`.
- Checksum SHA-256 por parte además del checksum del backup completo.
- Chequeo de espacio en disco antes de dividir (aborta con mensaje claro en
  vez de llenar el disco).

### Changed
- Backups nuevos usan extensión `.dump` (formato custom de `pg_dump`, no
  cambia); `.sql.gz` queda como alias legado, ambos reconocidos por
  contenido, no por extensión.
- `pg_dump` agrega `--no-owner --no-privileges` para portabilidad entre
  entornos con usuarios de base de datos distintos.
- `ruc_tool_backups` (esquema local) gana columnas `manifest_path`,
  `total_parts`, `part_size_bytes` (migración idempotente vía `init`).

### Compatibility
- Restore de un solo archivo (`.dump`/`.sql.gz`) sin cambios de
  comportamiento.
- Se evaluó cambiar el dump a `--data-only` para igualar exactamente el
  mecanismo actual de CodeRED-Platform; se decidió NO hacerlo en esta
  versión porque el restore propio de esta herramienta (`--clean
  --if-exists`) depende de que el dump traiga schema. Ver README.md,
  sección "Por qué el dump conserva el schema".

## [2.2.0] - 2026-08-06

### Added
- ✅ Herramienta CLI standalone completa para importación RUC
- ✅ Soporte para múltiples formatos: CSV, XLSX
- ✅ Validación automática de registros (formato, duplicados, checksum)
- ✅ Sistema de backup/restore con compresión gzip
- ✅ Búsqueda avanzada de registros
- ✅ Estadísticas en tiempo real
- ✅ Exportación a CSV y JSON
- ✅ Soporte para SQLite, PostgreSQL y MySQL
- ✅ Streaming de archivos grandes (sin límites de memoria)
- ✅ Batch processing optimizado (1000 registros/transacción)
- ✅ Sistema de logging completo
- ✅ Configuración flexible en JSON
- ✅ Progress bar visual
- ✅ Tests unitarios
- ✅ Docker support
- ✅ Documentación completa (README + DEVELOPMENT guide)

### Comandos disponibles
- `init` - Inicializar base de datos
- `import` - Importar archivo CSV/XLSX
- `validate` - Validar sin importar
- `export` - Exportar datos a CSV/JSON
- `stats` - Ver estadísticas
- `search` - Buscar registros
- `backup` - Crear backup
- `restore` - Restaurar desde backup
- `clean-duplicates` - Eliminar duplicados
- `config` - Administrar configuración

### Performance
- Velocidad: 40,000 - 60,000 registros/segundo
- Memoria: O(1) - streaming
- Compresión: gzip nivel 6
- Batch size: 1000 registros/transacción

### Estructura
- PHP 8.3
- Symfony Console 7.0
- Doctrine DBAL 4.0
- League CSV 9.14
- PHPOffice Spreadsheet 2.0
- Monolog 3.0

---

## [1.0.0] - 2026-06-01

### Initial Release
- Basic import functionality
- Simple validation
- SQLite support only

---

## Roadmap

### Próximas versiones
- [ ] API REST (Express/Node)
- [ ] Web UI dashboard
- [ ] Soporte para importación desde URLs
- [ ] Procesamiento paralelo multithread
- [ ] Integración con n8n mejorada
- [ ] Support para formatos: Parquet, ORC
- [ ] Sincronización con bases de datos externas
- [ ] WebSockets para monitoreo en vivo
- [ ] Ejecutable compilado (.phar)
- [ ] Soporte para GPG encryption

---

**Formato**: Este changelog sigue [Keep a Changelog](https://keepachangelog.com/) y versioning [Semantic Versioning](https://semver.org/)
