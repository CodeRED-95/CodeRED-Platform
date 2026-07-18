# 0020. Índice único parcial para `agencies.source` y `agencies.source_reference`

**Estado:** Aceptado

## Contexto

El módulo `Agencies` importa datos desde distintas fuentes, especialmente GitHub Gist. Algunas filas pueden repetir `source_reference` entre fuentes distintas, y otras pueden no tener `source_reference`.

## Problema

La restricción heredada `agencies_source_reference_unique` no expresa correctamente la regla de negocio. Además, en PostgreSQL una restricción `UNIQUE` no debe tratarse como si fuera un índice independiente.

## Alternativas consideradas

- Mantener una restricción `UNIQUE` tradicional.
- Eliminar toda unicidad y confiar en validación de aplicación.
- Usar un índice único parcial sobre `(source, source_reference)` cuando `source_reference IS NOT NULL`.

## Decisión

Se reemplaza la restricción heredada por un índice único parcial sobre `source` y `source_reference`.

## Justificación

- Permite repetir `source_reference` entre fuentes distintas.
- Permite múltiples valores `NULL`.
- Protege el caso realmente relevante para importación e idempotencia.
- Expresa mejor la regla de negocio que una restricción global.

## Consecuencias

- La migración debe validar duplicados antes de crear el índice.
- Si existen conflictos, la migración debe detenerse en lugar de alterar datos silenciosamente.
- El `down()` debe restaurar unicidad solo si no introduce colisiones.

## Referencias

- [database/migrations/2026_07_17_000009_adjust_agency_source_reference_index.php](../../database/migrations/2026_07_17_000009_adjust_agency_source_reference_index.php)
- [docs/DATABASE.md](../DATABASE.md)
