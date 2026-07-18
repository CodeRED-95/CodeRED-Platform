# ADR 0026: Autenticación tradicional por sesión para el login

## Estado

Aprobado

## Contexto

El login del proyecto pasó por varias iteraciones con Livewire, Alpine y sincronización automática de campos. Aunque la UI se veía correcta, el flujo terminó siendo inestable para el envío de credenciales y exponía el riesgo de degradarse a comportamientos no deseados del navegador.

## Problema

La pantalla de acceso no debía depender de la hidración de Livewire ni de la sincronización Alpine para funcionar correctamente. Un fallo de JavaScript o de inicialización podía derivar en envíos incorrectos, pérdida de estado o exposición accidental de credenciales en la URL.

## Alternativas evaluadas

1. Mantener el login en Livewire y seguir endureciendo la sincronización de campos.
2. Reforzar el login Livewire con más lógica de autocompletado y listeners DOM.
3. Migrar el login a un formulario Laravel tradicional con POST, CSRF y controlador de sesión.

## Decisión

Se adopta la tercera opción: el login de CodeRED Platform será tradicional por sesión.

## Justificación

- `POST /login` funciona aunque JavaScript falle.
- CSRF se mantiene de forma nativa.
- La autenticación por sesión es el camino más estable para una pantalla crítica.
- Livewire queda reservado para módulos administrativos donde sí aporta valor.

## Consecuencias

- El login deja de depender de `wire:submit`, `wire:model` y la hidratación de Livewire.
- El diseño visual se conserva mediante Blade y el CodeRED Design System.
- Livewire sigue disponible para Usuarios, Agencias, Dashboard y otras pantallas administrativas.
- La documentación y las pruebas deben verificar explícitamente `GET /login`, `POST /login`, CSRF y autenticación por sesión.
