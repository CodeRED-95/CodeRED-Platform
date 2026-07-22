# ADR 0039: importar el padrón RUC mediante streaming, COPY y checkpoints

- Estado: aceptado
- Fecha: 2026-07-21

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
