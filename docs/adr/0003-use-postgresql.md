# 0003 - Uso de PostgreSQL como base de datos principal

## Estado

Aprobado

## Contexto

El proyecto requiere consistencia transaccional, búsqueda avanzada, `jsonb`, índices compuestos y soporte para extensiones como `unaccent` y `pg_trgm`.

## Problema

Elegir una base de datos capaz de soportar crecimiento, búsquedas tolerantes a errores y almacenamiento estructurado de importaciones.

## Alternativas consideradas

- MySQL
- SQLite
- PostgreSQL

## Decisión

Usar PostgreSQL 16 como fuente de verdad.

## Justificación

- soporta `jsonb`
- permite extensiones útiles para búsqueda
- se adapta bien a filtros y relaciones del módulo Agencies
- facilita índices compuestos y búsquedas avanzadas

## Consecuencias

- Positivas:
  - mayor capacidad para búsquedas avanzadas
  - buen soporte de integridad referencial
- Negativas:
  - requiere configuración más explícita que SQLite

## Referencias

- `config/database.php`
- `database/migrations/2026_07_17_000006_enable_postgres_search_extensions.php`

