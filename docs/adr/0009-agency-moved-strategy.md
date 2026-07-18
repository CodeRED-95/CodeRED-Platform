# 0009 - Estrategia de agencias trasladadas

## Estado

Aprobado

## Contexto

Una agencia puede dejar de operar en un local y trasladarse a otro sin perder trazabilidad.

## Problema

Necesitamos preservar el historial y evitar eliminar la agencia antigua, pero sin mostrarla como operativa.

## Alternativas consideradas

- Eliminar la agencia antigua
- Sobrescribir el registro existente
- Marcarla como trasladada y mantenerla accesible

## Decisión

Mantener la agencia como registro histórico con `status = moved` y `has_moved = true`.

## Justificación

- conserva el historial
- evita pérdida de referencia externa
- permite mostrar aviso de traslado

## Consecuencias

- Positivas:
  - trazabilidad
  - mejor experiencia para usuarios que buscan el local antiguo
- Negativas:
  - añade complejidad al listado y al snapshot

## Referencias

- `app/Modules/Agencies/Actions/ApplyAgencyMoveAction.php`
- `app/Modules/Agencies/Resources/AgencyResource.php`

