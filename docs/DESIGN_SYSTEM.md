# CodeRED Design System

## Propósito

El CodeRED Design System estandariza la interfaz del proyecto con componentes Blade reutilizables, tokens semánticos y una paleta oscura propia de la marca.

## Principios visuales

| Principio | Descripción |
|---|---|
| Claridad | La interfaz prioriza legibilidad, jerarquía y densidad controlada. |
| Cohesión | Los módulos comparten componentes y tokens, evitando estilos aislados. |
| Identidad | El rojo CodeRED se usa como acento, no como fondo dominante. |
| Mantenibilidad | Los componentes Blade reducen duplicación y facilitan cambios globales. |
| Accesibilidad | Foco visible, contraste suficiente y navegación por teclado. |

## Paleta

| Token | Uso |
|---|---|
| `--color-background` | Fondo general |
| `--color-background-elevated` | Superficies elevadas |
| `--color-sidebar` | Barra lateral |
| `--color-surface` | Tarjetas y paneles |
| `--color-border` | Bordes principales |
| `--color-brand` | Acento CodeRED |
| `--color-accent-ivory` | Detalle premium |

## Tipografía

| Rol | Fuente |
|---|---|
| Interfaz y texto | `Inter`, `Manrope`, `system-ui` |
| Títulos | `Space Grotesk`, `Manrope` |
| Datos técnicos | `JetBrains Mono`, `monospace` |

## Componentes base

- `x-ui.logo`
- `x-ui.button`
- `x-ui.input`
- `x-ui.textarea`
- `x-ui.select`
- `x-ui.checkbox`
- `x-ui.toggle`
- `x-ui.card`
- `x-ui.stat-card`
- `x-ui.badge`
- `x-ui.alert`
- `x-ui.modal`
- `x-ui.table`
- `x-ui.empty-state`
- `x-ui.loading`
- `x-ui.pagination`
- `x-ui.breadcrumb`
- `x-ui.page-header`
- `x-ui.section-header`

## Variantes semánticas

### Botones

| Variante | Uso |
|---|---|
| `primary` | Acción principal |
| `secondary` | Acción secundaria |
| `outline` | Acción neutra |
| `ghost` | Interacción sutil |
| `danger` | Acción destructiva |

### Badges

| Tono | Significado |
|---|---|
| `success` | Activa |
| `neutral` | Inactiva |
| `info` | En revisión |
| `warning` | Trasladada |
| `brand` | Centro de Operaciones |
| `ivory` | Tamaños o etiquetas especiales |

## Uso del logo

| Contexto | Variante |
|---|---|
| Login y bienvenida | Completo |
| Sidebar y favicon | Símbolo |
| Formatos cuadrados | Cuadrado |

## Accesibilidad

- Los controles deben tener foco visible.
- Los icon buttons deben tener `aria-label`.
- Los modales deben bloquear scroll y ser cerrables con Escape.
- No depender solo del color para transmitir estado.

## Qué no hacer

- No usar colores hexadecimales sueltos si existe un token.
- No instalar Bootstrap o frameworks visuales alternativos.
- No copiar identidades ajenas.
- No crear componentes redundantes.

## Crear un componente nuevo

1. Verificar si un componente existente cubre el caso.
2. Si no existe, crear un componente Blade en `resources/views/components/ui/`.
3. Definir una API clara con `props`.
4. Documentarlo en este archivo.
5. Reutilizarlo en login, dashboard, módulo Agencies o design system.
