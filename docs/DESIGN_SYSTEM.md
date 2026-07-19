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
- `x-ui.search-box`
- `x-ui.textarea`
- `x-ui.dropdown-select`
- `x-ui.status-select`
- `x-ui.checkbox`
- `x-ui.toggle`
- `x-ui.card`
- `x-ui.stat-card`
- `x-ui.confirm-dialog`
- `x-ui.toast-stack`
- `x-ui.spinner`
- `x-ui.skeleton`

## Contratos de componentes

| Componente | Propiedades principales | Uso |
|---|---|---|
| `x-ui.input` | `label`, `type`, `error`, `wrapperClass`, slots `prefix` y `suffix` | Entrada simple o con acciones internas |
| `x-ui.search-box` | `label`, `placeholder`, `error`, atributos `wire:model*` | Búsquedas y filtros de texto |
| `x-ui.dropdown-select` | `id`, `name`, `label`, `value`, `options`, `required`, `disabled`, `error` | Selección simple accesible |
| `x-ui.status-select` | API de dropdown más iconos de estado | Estados de agencias |
| `x-ui.confirm-dialog` | `id`, `title`, `message`, `confirmLabel`, `confirmAction`, `tone`, slot `trigger` | Confirmar acciones sensibles |
| `x-ui.toast-stack` | `messages`, `duration`; evento global `toast` | Feedback temporal global |
| `x-ui.spinner` | `size`, `label` | Operaciones breves en curso |
| `x-ui.skeleton` | `variant`, `rows` | Reserva visual mientras carga contenido |
| `x-ui.button` | `variant`, `size`, `type`, `href`, `disabled`, `loading` | Acciones y navegación |
| `x-ui.alert` | `tone` | Mensajes persistentes de sistema o validación |

Los atributos no declarados se propagan al elemento interactivo. Esto permite usar
`wire:model`, `wire:click`, `wire:loading`, ARIA y atributos HTML sin duplicar APIs.

`x-ui.dropdown` es un menú de acciones arbitrarias. `x-ui.dropdown-select` representa
un único valor de formulario con patrón listbox; no son componentes intercambiables.

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
Los contratos reutilizables se verifican en `DesignSystemComponentsTest`.

## Feedback y carga

El layout contiene una sola instancia de `x-ui.toast-stack`. Acepta flashes `success`
y `error`, además del evento Livewire/DOM `toast` con `tone` o `type` y `message`.
Los toasts se anuncian mediante `aria-live`, pueden cerrarse y expiran automáticamente.

Usar `x-ui.spinner` dentro de acciones cortas. Para tablas, tarjetas o texto que aún no
están disponibles, usar `x-ui.skeleton`. `x-ui.loading` se mantiene como fachada
compatible sobre skeleton. Los botones Livewire aceptan `loadingTarget` y
`loadingLabel` para evitar duplicar estados de carga.

## Dashboard

El dashboard combina `x-ui.stat-card`, `x-ui.card`, `x-ui.section-header`, badges e
iconos de estado. Las consultas y agregaciones pertenecen al componente Livewire;
las vistas Blade solo presentan los datos recibidos.

- Las tarjetas admiten `description`, icono, tono semántico y enlace opcional.
- Los conteos de usuarios requieren autorización de `UserPolicy`.
- Los datos de agencias requieren `viewAny` sobre `Agency`.
- Los gráficos sin librerías externas deben conservar valores textuales, etiquetas
  accesibles y significado independiente del color.
- “Usuarios nuevos” representa los últimos 30 días; la tendencia de agencias resume los últimos 7 días.

## Base validada en Sprint 01

### Tokens visuales

Los tokens se declaran una sola vez en `resources/css/app.css` y los componentes los consumen mediante clases Tailwind arbitrarias.

| Categoría | Tokens | Contrato |
|---|---|---|
| Fondos | `--color-background`, `--color-background-elevated`, `--color-surface` | Página, panel elevado y control/tarjeta |
| Bordes | `--color-border`, `--color-border-subtle` | Controles y divisores |
| Texto | `--color-text-primary`, `--color-text-secondary`, `--color-text-muted`, `--color-text-disabled` | Jerarquía y estado deshabilitado |
| Semántica | `--color-brand`, `--color-success`, `--color-warning`, `--color-danger`, `--color-info` | Acciones y estados; nunca sustituyen texto o iconos |
| Foco | `.focus-ring` | Ring de marca con separación respecto al fondo |
| Radios | `--radius-control`, `--radius-card`, `--radius-modal`, `--radius-panel` | Jerarquía coherente de superficies |
| Sombras | `--shadow-control`, `--shadow-elevated` | Control y panel flotante |
| Dimensiones | `--control-height`, `--icon-size-control`, `--space-section` | Altura mínima, icono y separación vertical |

El tema oscuro continúa siendo el contrato principal. Este sprint no introduce un segundo sistema temático.

### Convención y composición

Los componentes compartidos viven bajo `resources/views/components/ui` y se consumen con `x-ui.*`. No crear componentes `x-form.*` que dupliquen contratos existentes.

- `x-ui.input` y `x-ui.textarea` componen `x-ui.form-label` y `x-ui.form-error`.
- `x-ui.card` acepta contenido libre y, opcionalmente, `title`, `description` y el slot `actions`.
- `x-ui.button` concentra variantes, tamaños y estados de carga.
- `x-ui.status-select` especializa `x-ui.dropdown-select`; sus valores pertenecen al enum de negocio y no se redefinen en Blade.

### Ejemplos reales

```blade
<x-ui.input
    id="email"
    wire:model.live="email"
    name="email"
    type="email"
    label="Correo electrónico"
    required
    :error="$errors->first('email')"
/>

<x-ui.button
    type="submit"
    loading-target="save"
    loading-label="Guardando…"
    wire:loading.attr="disabled"
    wire:target="save"
>
    Guardar
</x-ui.button>

<x-ui.card title="Perfil" description="Datos generales">
    <x-slot:actions>
        <x-ui.button variant="secondary" size="sm">Editar</x-ui.button>
    </x-slot:actions>
    Contenido del perfil.
</x-ui.card>
```

Los attribute bags propagan `wire:model`, sus modificadores, atributos Alpine, `disabled`, `readonly`, `autocomplete`, `placeholder` y atributos ARIA al elemento interactivo.

### Accesibilidad de formularios

- Todo control con etiqueta genera una asociación explícita `for`/`id`.
- Los errores tienen un ID estable y se conectan mediante `aria-describedby`.
- `aria-invalid` comunica el estado sin depender del borde rojo.
- Los campos obligatorios incluyen indicador visual y texto para lector de pantalla.
- Los iconos decorativos usan `aria-hidden`; los botones de solo icono requieren `aria-label`.
- El foco visible se implementa con `.focus-ring`.
- Alpine se obtiene exclusivamente desde Livewire 3; no se importa ni inicializa otra instancia.

### Pantalla piloto

`resources/views/livewire/account/change-password.blade.php` valida la arquitectura base con tres inputs Livewire y un submit. Conserva las propiedades, validaciones, ruta y persistencia originales, y añade errores asociados, instrucciones y loading accesible.

### Incorporar un componente

1. Confirmar que ningún `x-ui.*` existente resuelve el patrón.
2. Definir un contrato pequeño y compatible con attribute bags.
3. Componer componentes base antes de copiar clases.
4. Añadir pruebas de renderizado, accesibilidad y compatibilidad Livewire/Alpine.
5. Documentar el componente y migrar una sola pantalla piloto antes de extenderlo.

No deben duplicarse controles, variantes puramente cosméticas, inicialización de Alpine, colores hexadecimales en vistas ni lógica de estados de negocio.

## Jerarquía de capas y overlays

La escala se centraliza en `resources/css/app.css`. Las vistas consumen clases semánticas y no números aislados:

| Capa | Token / clase | Nivel |
|---|---|---|
| Contenido | `--layer-content` / `.layer-content` | 0 |
| Elevación local | `--layer-raised` / `.layer-raised` | 10 |
| Header | `--layer-header` / `.layer-header` | 30 |
| Sidebar | `--layer-sidebar` / `.layer-sidebar` | 40 |
| Dropdowns y popovers | `--layer-popover` / `.layer-popover` | 50 |
| Backdrop | `--layer-backdrop` / `.layer-backdrop` | 60 |
| Modales y confirmaciones | `--layer-modal` / `.layer-modal` | 70 |
| Toasts globales | `--layer-toast` / `.layer-toast` | 80 |
| Mensajes críticos | `--layer-critical` / `.layer-critical` | 90 |

`backdrop-filter`, `transform`, `opacity`, `isolation` y un elemento posicionado con `z-index` pueden crear contextos de apilamiento. Un hijo no escapa de ese contexto aumentando su `z-index`. Del mismo modo, `overflow-hidden`, `overflow-clip` y los contenedores con scroll recortan descendientes flotantes.

`x-ui.dropdown-select`, `x-ui.status-select` y `x-ui.dropdown` conservan el trigger en el flujo del documento, pero teletransportan el panel a `body` mediante `x-teleport`. El posicionador Alpine compartido `codeRedFloating` usa `position: fixed`, `getBoundingClientRect()`, límites del viewport y apertura superior cuando falta espacio inferior. Recalcula en scroll y resize y elimina todos sus listeners en `destroy()`, incluido el cierre por `livewire:navigating`.

Los nuevos dropdowns deben reutilizar ese posicionador. No deben volver a crear un panel `absolute` dentro de una card. Se puede usar `overflow-visible` cuando el contenedor no necesita recorte; tablas responsivas, mapas, imágenes y zonas con scroll deben conservar su overflow y resolver overlays mediante portal.

`x-ui.modal` y `x-ui.confirm-dialog` también se teletransportan a `body`, bloquean el scroll y usan la capa modal. `x-ui.toast-stack` se monta una sola vez como hijo directo de `body`, fuera del shell con sidebar, en `#global-toast-region`; permanece fijo, permite interacción solo en cada toast y se anuncia con `aria-live`.

## Vista cartográfica integrada

`x-ui.map-preview` usa Leaflet 1.9 y tiles de OpenStreetMap sin API key. La instancia se integra con Alpine y Livewire mediante `wire:ignore`, se destruye antes de `wire:navigate`, recalcula su tamaño y muestra zoom, attribution, popup seguro, enlace externo y marcador CodeRED. El mapa no sustituye ni transforma los campos de latitud y longitud.

```blade
<x-ui.map-preview
    :latitude="$agency->latitude"
    :longitude="$agency->longitude"
    :label="'Ubicación de '.$agency->name"
/>
```

## Dashboard operativo

El Dashboard ejecutivo usa una jerarquía compacta y datos reales:

- cuatro KPIs principales: total de agencias, activas, en revisión y total de usuarios;
- resumen secundario de usuarios nuevos, estados menos prioritarios, importaciones del periodo y errores del último proceso;
- selector funcional de 7, 30 o 90 días, aplicado solo a métricas temporales;
- tendencia calculada mediante agregación SQL y representada con línea/área SVG, eje Y desde cero y tooltips nativos;
- donut SVG con total central, cinco estados, cantidades y porcentajes seguros cuando el total es cero;
- actividad limitada a seis eventos, filtrada por permisos y cargada con `actor`/`auditable` mediante eager loading;
- última importación compacta con estados traducidos y contadores persistidos en `agency_imports`.

En escritorio, gráficos y paneles recientes usan proporciones 65/35 aproximadas; en móvil se apilan sin scroll horizontal. Los SVG declaran siempre `fill="none"` en ejes y trazos abiertos para evitar rellenos negros implícitos. Incluyen resumen textual y no requieren una segunda librería de gráficos.
