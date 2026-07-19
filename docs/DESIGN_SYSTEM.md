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
- `x-ui.dropdown-select`
- `x-ui.status-select`
- `x-ui.checkbox`
- `x-ui.toggle`
- `x-ui.card`
- `x-ui.stat-card`

## Combobox para relaciones grandes

Cuando una relación simple tiene demasiados registros para un `<select>` nativo, el sistema visual oficial usa un combobox buscable.

No usar:

- `<select multiple>`
- `<select size="...">`
- dos controles distintos para el mismo campo

Sí usar:

- un único control cerrado por defecto;
- búsqueda con debounce;
- lista con scroll interno;
- estilos oscuros del sistema;
- exclusión del registro actual y de registros inválidos desde servidor.
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

## Selector de estados de agencias

`x-ui.dropdown-select` es el control base para selecciones simples. Renderiza botones,
un listbox oscuro y un input oculto; nunca delega la interfaz a `select` u `option`.
`x-ui.status-select` configura su variante con los iconos y valores de `AgencyStatus`.
Ambos sincronizan con Livewire, ofrecen selección visible y navegación mediante teclado.

El botón expone el patrón ARIA `combobox`/`listbox`, cierra con Escape o clic exterior,
y permite recorrer las opciones con flechas y confirmar con Enter. La selección se
sincroniza mediante un input oculto que conserva el `wire:model` original.

Las vistas Blade no deben introducir controles `select` nativos. Los nuevos catálogos
simples deben proporcionar un arreglo `valor => etiqueta` a `x-ui.dropdown-select`.

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
- No añadir inicialización manual de Alpine en `resources/js/app.js` si la página ya se apoya en `@livewireScripts`; los componentes deben seguir siendo compatibles con la instancia de Alpine que Livewire 3 expone.

## Crear un componente nuevo

1. Verificar si un componente existente cubre el caso.
2. Si no existe, crear un componente Blade en `resources/views/components/ui/`.
3. Definir una API clara con `props`.
4. Documentarlo en este archivo.
5. Reutilizarlo en login, dashboard, módulo Agencies o design system.

## Reglas de consumo en vistas

- Usar `x-ui.input`, `x-ui.textarea`, `x-ui.checkbox` o `x-ui.toggle` para formularios.
- Usar `x-ui.button` e `x-ui.icon-button` para acciones estándar.
- Usar `x-ui.card`, `x-ui.page-header` y `x-ui.section-header` para estructura visual.
- Mostrar validaciones con la propiedad `error` del control o con `x-ui.form-error`.
- Mostrar resultados globales con `x-ui.alert`; el tono se configura mediante `tone`.
- Reservar controles HTML manuales para patrones especializados como comboboxes con búsqueda o campos con acciones internas.
- No usar manejadores JavaScript inline; las interacciones de vista se implementan con Alpine.js.

La consistencia se protege con `DesignSystemConsistencyTest` y `NativeSelectRemovalTest`.
