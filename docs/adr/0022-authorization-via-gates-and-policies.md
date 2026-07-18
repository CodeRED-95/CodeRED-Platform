# 0022. Autorización vía Gates y Policies sin sobrescribir `User::can()`

**Estado:** Aceptado

## Contexto

Laravel ya expone autorización nativa mediante `can()`, `cannot()`, `Gate`, Policies y middleware. En CodeRED Platform se necesita resolver permisos almacenados en PostgreSQL sin romper ese contrato.

## Problema

Sobrescribir `User::can()` rompe la compatibilidad con `Authenticatable` y puede producir errores fatales por firma incompatible. Además, debilita la interoperabilidad con Blade, middleware `can`, Livewire, Sanctum y Policies.

## Alternativas consideradas

- Sobrescribir `User::can()`.
- Mover la lógica a helpers explícitos y dejar `can()` nativo.
- Implementar todo en Policies sin `Gate::before`.

## Decisión

Se mantiene `User::can()` sin sobrescrituras y se resuelve la autorización mediante:

- helpers explícitos en `User`
- `Gate::before` para permisos almacenados en PostgreSQL
- Policies para acciones por modelo

## Justificación

- Conserva la compatibilidad del framework.
- Mantiene Blade, middleware, Livewire y Sanctum funcionando de forma nativa.
- Permite extender la autorización sin duplicar lógica.

## Consecuencias

- La lógica de permisos debe vivir en métodos explícitos como `hasPermission()` y `hasRole()`.
- `Gate::before` se convierte en el puente entre permisos en base de datos y la autorización nativa.
- Las Policies deben delegar en los helpers de dominio o en reglas de negocio claras.

## Referencias

- [app/Models/User.php](../../app/Models/User.php)
- [app/Providers/AppServiceProvider.php](../../app/Providers/AppServiceProvider.php)
- [docs/AUTHORIZATION.md](../AUTHORIZATION.md)
