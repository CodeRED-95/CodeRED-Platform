# 0013 - Reutilización de una sola imagen PHP para app, queue y scheduler

## Estado

Aprobado

## Contexto

Los servicios `app`, `queue` y `scheduler` comparten la misma base PHP, extensiones y dependencias de ejecución.

## Problema

Cómo evitar discrepancias entre servicios que ejecutan el mismo código con necesidades técnicas iguales.

## Alternativas consideradas

- Imágenes separadas por servicio
- Una imagen PHP compartida

## Decisión

Construir una sola imagen PHP reutilizable para `app`, `queue` y `scheduler`.

## Justificación

- reduce duplicación
- simplifica mantenimiento
- asegura que los tres servicios tengan las mismas extensiones y permisos

## Consecuencias

- Positivas:
  - builds más coherentes
  - menor riesgo de divergencia
- Negativas:
  - un cambio de imagen afecta a los tres servicios

## Referencias

- `docker-compose.yml`
- `docker/php/Dockerfile`

