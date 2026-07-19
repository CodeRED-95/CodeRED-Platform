# Seeders

## Objetivo

Los seeders del proyecto preparan datos iniciales para desarrollo y pruebas sin duplicar información.

## Estructura actual

| Seeder | Responsabilidad |
|---|---|
| `DatabaseSeeder` | Orquestador principal |
| `RolesSeeder` | Crea/actualiza roles |
| `PermissionsSeeder` | Crea/actualiza permisos |
| `SettingsSeeder` | Crea/actualiza configuraciones base |
| `AdminSeeder` | Crea/actualiza el administrador de desarrollo |
| `AgencySeeder` | Crea datos demo de agencias |

## Reglas

- `DatabaseSeeder` no debe contener toda la lógica de negocio.
- Los seeders deben ser idempotentes.
- Los datos de administración deben usar `updateOrCreate()`.
- Las contraseñas siempre deben pasar por `Hash::make()`.
- Los datos demo deben crearse solo si la tabla está vacía.

## Orden recomendado

1. Roles
2. Permisos
3. Configuración
4. Administrador
5. Agencias demo

## Factories

Las seeders que crean datos demo deben usar factories del proyecto. En módulos Laravel, si la factory está en `database/factories`, el modelo debe declarar `newFactory()`.

## Roles y permisos

`RolesSeeder` conserva únicamente `super-admin`, `viewer` y `editor`. `PermissionsSeeder` sincroniza la matriz exacta: Super Administrador recibe todos los permisos, Consulta solo `agencies.view` y Editor recibe Dashboard y gestión no destructiva de Agencias. Los usuarios heredados con `admin` pasan a Editor salvo que ya sean Super Administrador.

Los permisos `api-tokens.*` se crean idempotentemente y solo quedan asociados a Super Administrador mediante la sincronización total; Editor y Consulta conservan sus matrices cerradas.
