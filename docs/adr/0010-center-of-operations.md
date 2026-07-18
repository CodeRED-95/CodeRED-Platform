# 0010 - Campo propio para Centro de Operaciones

## Estado

Aprobado

## Contexto

El JSON del Gist incluye el campo `co`, que en el dominio actual significa Centro de Operaciones.

## Problema

Se necesita representar ese dato sin ambigüedad en el modelo interno y en la interfaz.

## Alternativas consideradas

- Mantener `co`
- Renombrarlo a `is_co`
- Renombrarlo a `is_operations_center`

## Decisión

Usar `is_operations_center` como nombre interno.

## Justificación

- evita ambigüedad semántica
- deja claro el significado funcional
- se alinea con la interfaz administrativa

## Consecuencias

- Positivas:
  - nomenclatura clara
  - menor riesgo de interpretación incorrecta
- Negativas:
  - exige mapear el campo al importar

## Referencias

- `app/Modules/Agencies/Support/AgencyImportNormalizer.php`
- `app/Modules/Agencies/Resources/AgencyResource.php`

