# 0004 - Uso de Livewire para la interfaz administrativa

## Estado

Aprobado

## Contexto

El panel administrativo necesita interacción rápida, búsqueda instantánea y formularios sin construir una SPA completa.

## Problema

Cómo construir una interfaz reactiva manteniendo el stack Laravel y evitando complejidad innecesaria.

## Alternativas consideradas

- SPA con React
- Inertia
- Livewire

## Decisión

Usar Livewire 3 para el panel y parte de la experiencia pública.

## Justificación

- mantiene lógica en Laravel
- reduce duplicación entre backend y frontend
- simplifica formularios y búsquedas
- encaja con el diseño actual del proyecto

## Consecuencias

- Positivas:
  - menor complejidad de frontend
  - más velocidad de entrega
- Negativas:
  - dependencia del ciclo de vida de Livewire

## Referencias

- `app/Livewire`
- `resources/views/livewire`

