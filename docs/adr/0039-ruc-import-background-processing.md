# ADR 0039: importar el padrón RUC mediante streaming, COPY y checkpoints

- Estado: **superado** (v3.0.0, 2026-08-09)
- Fecha: 2026-07-21
- Superado por: eliminación del sistema de importación RUC. El padrón se
  administra ahora exclusivamente mediante backup/restore de `ruc_records`.
  Ver [docs/RUC_MODULE.md](../RUC_MODULE.md).

> **Nota histórica.** Este ADR se conserva como registro de la decisión original
> y de su contexto. Nada de lo que describe sigue existiendo en el código: las
> tablas `ruc_imports`, `ruc_staging`, `ruc_import_*`, la cola `ruc-imports` y
> las clases de importación se eliminaron en la v3.0.0.

## Contexto

El padrón reducido SUNAT puede contener aproximadamente 18 millones de líneas y se reemplaza operativamente cada uno o dos meses. No es viable subirlo por Livewire, crear un job por fila ni usar Eloquent individualmente.

## Decisión

Los TXT se colocan en `storage/app/private/ruc/incoming` y se registran desde el panel o CLI. Un worker de la cola `ruc-imports` procesa el stream, precarga el catálogo UBIGEO, carga `ruc_staging` con PostgreSQL COPY y confirma checkpoints con byte offset. Al final ejecuta un merge `ON CONFLICT (ruc) DO NOTHING` hacia `ruc_records`.

## Consecuencias

- El proceso mantiene memoria acotada y soporta archivos de decenas de millones de filas.
- Interrupciones pueden reanudarse desde el último checkpoint confirmado.
- Los RUC existentes nunca se sobrescriben durante esta importación.
- PostgreSQL, Redis y el worker compartiendo el volumen privado son requisitos operativos.
- Update.sh preserva un worker que tenga una importación RUC activa.
