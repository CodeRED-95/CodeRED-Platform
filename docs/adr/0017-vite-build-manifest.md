# 0017. Compilación obligatoria de Vite para generar el manifest

**Estado:** Aceptado

## Contexto

La interfaz usa Vite para compilar los assets frontend de Laravel, Livewire, TailwindCSS y AlpineJS.

## Problema

Si `public/build/manifest.json` no existe, la vista de login y otras páginas pueden fallar con `ViteManifestNotFoundException`.

## Alternativas consideradas

- Compilar assets solo en producción.
- Compilar assets manualmente después de cada cambio.
- Requerir `npm run build` como parte del flujo de instalación y despliegue.

## Decisión

Se requiere `npm run build` para producir `public/build/manifest.json` y los assets compilados necesarios.

## Justificación

- Garantiza que Nginx pueda servir los assets correctamente.
- Evita que el panel falle por un manifest ausente.
- Mantiene el flujo predecible en desarrollo y despliegue.

## Consecuencias

- El procedimiento de instalación debe documentar la compilación frontend.
- Las verificaciones automatizadas deben confirmar que el manifest exista.

## Referencias

- [docs/INSTALL.md](../INSTALL.md)
- [docs/TROUBLESHOOTING.md](../TROUBLESHOOTING.md)
