# ADR 0029: Portal global para overlays visuales

## Estado

Aceptado — 2026-07-19.

## Contexto

Las cards del Design System aplican `backdrop-filter`, lo que crea contextos de apilamiento. Los listboxes y diálogos renderizados dentro de cards o tablas no podían superar superficies hermanas y podían ser recortados por contenedores con overflow. Aumentar números de `z-index` locales no resolvía ese límite del DOM.

## Decisión

- Centralizar la escala de capas mediante tokens y clases semánticas en `resources/css/app.css`.
- Renderizar listboxes, menús, modales y confirmaciones en `body` con `x-teleport` de Alpine 3.
- Posicionar paneles flotantes con un único helper Alpine basado en `position: fixed` y `getBoundingClientRect()`.
- Recalcular posición en scroll/resize, abrir hacia arriba cuando corresponda y limpiar listeners al destruir el componente o navegar con Livewire.
- Mantener una única región global de toasts como hija directa de `body`.
- Conservar overflow donde sea funcionalmente necesario; los overlays deben escapar mediante portal.

## Consecuencias

Los overlays dejan de depender del stacking context de la vista que los invoca. Los IDs y relaciones ARIA siguen conectando trigger y panel aunque el panel cambie de rama DOM. Todo componente flotante nuevo debe reutilizar esta arquitectura y las verificaciones frontend deben incluir scroll, resize y navegación Livewire.