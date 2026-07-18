# 0006 - Importación desde GitHub Gist

## Estado

Aprobado

## Contexto

La fuente real de datos de agencias proviene de un JSON alojado en GitHub Gist. La estructura real observada no coincide con el modelo interno de CodeRED Platform.

## Problema

Importar datos externos de forma segura, transformando campos heterogéneos al modelo interno sin asumir campos inexistentes.

## Alternativas consideradas

- Importar desde CSV únicamente
- Importar desde Gist raw
- Importar desde API propia

## Decisión

Aceptar URLs raw de GitHub Gist bajo validación SSRF y transformar la estructura externa al modelo interno.

## Justificación

- permite consumir la fuente real de datos
- evita copiar manualmente grandes volúmenes de información
- mantiene un origen trazable mediante `source_reference`

## Consecuencias

- Positivas:
  - importación más flexible
  - trazabilidad de origen
- Negativas:
  - requiere validación estricta
  - puede haber cambios de esquema externo

## Referencias

- `app/Modules/Agencies/Support/AgencyImportNormalizer.php`
- `app/Modules/Agencies/Services/AgencyImportPreviewService.php`

