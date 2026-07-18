# 0025. Panel Agencies con Livewire y Actions centralizadas

## Estado

Aceptado

## Contexto

El módulo `Agencies Shalom` debía ofrecer administración, detalle, importación, traslado, vista pública y API pública sin duplicar lógica entre pantallas.

## Problema

Había riesgo de mezclar validación, persistencia, importación y reglas de traslado directamente en Livewire o en controladores, lo que dificultaría el mantenimiento.

## Alternativas consideradas

1. Implementar todo en controladores y vistas Blade.
2. Crear una capa pesada de repositorios y servicios genéricos.
3. Centralizar las operaciones mutables en Actions y exponer la UI con Livewire.

## Decisión

Se decidió usar Livewire para la UI administrativa y pública, mientras que la lógica de negocio mutable se centraliza en `Actions` y `Services`.

## Justificación

- Livewire encaja con el dashboard existente.
- Actions permiten encapsular traslado e importación sin duplicar reglas.
- Services permiten reutilizar búsquedas, normalización y vista previa.
- Se mantiene la compatibilidad con Policies, Gates, API Resources y auditoría.

## Consecuencias

- El panel queda más fácil de mantener.
- Los cambios de negocio se concentran en pocos puntos.
- Las pruebas pueden enfocarse en Actions, Services y componentes Livewire.

## Referencias

- `app/Livewire/Admin/Agencies`
- `app/Modules/Agencies/Actions`
- `app/Modules/Agencies/Services`
- `app/Modules/Agencies/Support`
