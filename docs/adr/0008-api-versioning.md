# 0008 - Versionado de API en `/api/v1`

## Estado

Aprobado

## Contexto

La API pública debe poder evolucionar sin romper consumidores existentes.

## Problema

Cómo proteger a clientes externos ante cambios futuros del contrato de API.

## Alternativas consideradas

- API sin versión
- Versionado por headers
- Versionado por ruta

## Decisión

Usar versionado por ruta bajo `/api/v1`.

## Justificación

- explícito y fácil de documentar
- simple de consumir
- compatible con la estructura actual de rutas

## Consecuencias

- Positivas:
  - claridad contractual
  - compatibilidad futura
- Negativas:
  - introduce prefijo permanente

## Referencias

- `routes/api.php`
- `routes/agencies.php`

