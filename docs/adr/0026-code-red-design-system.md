# 0026. CodeRED Design System

## Estado

Aceptado

## Contexto

CodeRED Platform necesitaba una interfaz consistente para el login, dashboard, panel administrativo, vistas públicas y futuros módulos. La base visual previa era funcional, pero demasiado dispersa y dependía de utilidades puntuales repetidas en varias vistas.

## Problema

Sin un sistema visual común:

- se duplican clases Tailwind;
- cada vista termina con estilos propios;
- la experiencia de marca se diluye;
- los cambios globales son costosos;
- la accesibilidad y el responsive quedan inconsistentes.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
|---|---|
| Bootstrap / AdminLTE | Rompe la identidad visual y duplica patrones innecesariamente. |
| Template administrativo completo | Añade abstracciones ajenas y dificulta la evolución modular. |
| Estilos locales por pantalla | Incrementa la deuda visual y la inconsistencia. |
| Solo utilidades Tailwind sueltas | Funciona al inicio, pero no escala como contrato visual. |

## Decisión

Adoptar un CodeRED Design System basado en:

- tokens CSS semánticos;
- componentes Blade reutilizables;
- Tailwind CSS como capa de implementación;
- Alpine.js para interacciones ligeras;
- branding rojo oscuro propio de CodeRED;
- página interna de referencia visual en `/admin/design-system`.

## Justificación

Esta estrategia mantiene la arquitectura Laravel existente, mejora la mantenibilidad y permite construir nuevos módulos sin crear estilos aislados. Blade Components reducen duplicación, y los tokens centralizan colores, radios, sombras y tipografía.

## Consecuencias

- Login, dashboard y panel administrativo comparten lenguaje visual.
- Los nuevos módulos deben reutilizar componentes existentes antes de crear estilos nuevos.
- Los cambios de marca pueden hacerse desde tokens y componentes en lugar de tocar cada vista.
- La documentación debe mantenerse sincronizada con el sistema visual.

## Referencias

- `resources/css/app.css`
- `resources/views/components/ui/`
- `docs/DESIGN_SYSTEM.md`
- `AGENTS.md`
- `docs/ARCHITECTURE.md`
