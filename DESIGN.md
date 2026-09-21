# CodeRED Platform — Sistema visual oni

## Dirección

Platform adopta la identidad de CodeRED Web: tinta profunda como espacio de
trabajo, texto sobre papel mineral y bermellón como señal de acción. El oni
vive en los emblemas de marca; no se reemplaza con caracteres decorativos ni
efectos de brillo.

## Operación

El panel es una superficie de trabajo. La navegación, tablas y formularios
mantienen densidad útil, contraste alto y estados inequívocos. Las tarjetas son
superficies de contenido, no adornos; la elevación se reserva para capas que la
necesitan.

## Tokens

- Fondo: `--color-background` y `--color-background-elevated`.
- Superficies: `--color-surface`, `--color-surface-hover` y bordes semánticos.
- Acción: `--color-brand` con texto mineral y foco visible.
- Tipografía: Inter para interfaz, Space Grotesk para jerarquía y JetBrains
  Mono para versiones, claves, medidas y datos técnicos.

## Componentes

Los componentes `x-ui.*` conservan sus contratos funcionales. Los iconos son
SVG mediante `x-ui.icon`; no se usan emojis ni glifos de texto como iconos.

## Accesibilidad y movimiento

Los controles mantienen foco, contraste y estados de error. `prefers-reduced-
motion` reduce las transiciones globales. La información está disponible sin
animación ni efectos de fondo.
