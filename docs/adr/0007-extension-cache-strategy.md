# 0007 - Estrategia de caché para la futura extensión Chrome

## Estado

Aprobado

## Contexto

La extensión Chrome necesitará consultar información compacta y rápida sin cargar payloads administrativos innecesarios.

## Problema

Cómo servir datos públicos de agencias con bajo costo y respuesta predecible.

## Alternativas consideradas

- Consultar siempre la base de datos
- Cachear snapshot compacto
- Requiere sincronización incremental completa

## Decisión

Preparar un snapshot compacto en la API pública y usar Redis para versionado y caché relacionada.

## Justificación

- permite respuestas livianas
- simplifica la invalidación por versión
- reduce consultas repetidas

## Consecuencias

- Positivas:
  - mejor rendimiento
  - mejor experiencia de consulta
- Negativas:
  - requiere invalidación disciplinada

## Referencias

- `app/Modules/Agencies/Support/AgencyVersion.php`
- `app/Http/Controllers/Api/V1/AgenciesController.php`

