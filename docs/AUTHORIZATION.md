# Autorización

## Principio

CodeRED Platform usa la autorización nativa de Laravel mediante `Gate`, Policies, middleware y los helpers explícitos de `User`. Nunca se sobrescribe `User::can()`.

## Roles definitivos

| Rol visible | Slug | Alcance |
|---|---|---|
| Super Administrador | `super-admin` | Acceso total y gestión administrativa |
| Consulta | `viewer` | Listado, detalle y mapa de agencias en lectura |
| Editor | `editor` | Dashboard y creación/edición de agencias, incluido su estado |

No se crean roles fuera de este catálogo desde la interfaz. El rol heredado `admin` se migra a `editor`, excepto cuando la cuenta ya conserva `super-admin`; nunca se asciende una cuenta automáticamente.

## Matriz

| Permiso | Super Administrador | Consulta | Editor |
|---|:---:|:---:|:---:|
| `dashboard.view` | Sí | No | Sí |
| `agencies.view` | Sí | Sí | Sí |
| `agencies.create` | Sí | No | Sí |
| `agencies.update` | Sí | No | Sí |
| `agencies.manage_status` | Sí | No | Sí |
| Eliminar, papelera y acciones destructivas | Sí | No | No |
| Importaciones | Sí | No | No |
| Usuarios y roles | Sí | No | No |
| Design System interno | Sí | No | No |

La vista del mapa se protege con `agencies.view`: es una representación geográfica de los mismos registros y no introduce un permiso duplicado.

## Flujo

1. `Gate::before` concede cualquier ability al rol `super-admin`.
2. Para un string de permiso real, consulta `hasPermission()`.
3. Las abilities de modelo continúan hasta su Policy; no se traducen globalmente por nombre, evitando que `viewAny` de Agencias habilite Usuarios accidentalmente.
4. Las rutas, componentes Livewire y acciones vuelven a autorizar en servidor.

## Policies y reglas críticas

`AgencyPolicy` mapea vista, creación, edición, estado, importación y operaciones destructivas a sus permisos de agencia. `UserPolicy` y `UserSecurityService` protegen las cuentas administrativas.

El último Super Administrador activo no puede eliminarse, desactivarse ni perder `super-admin`. La comprobación ocurre en servidor, incluso si otro Super Administrador intenta modificarlo.

## Perfil propio

`/profile` siempre obtiene la cuenta con `auth()->user()`; no acepta un ID de usuario. Permite nombre, correo y contraseña, pero no rol, permisos, estado ni campos administrativos. El cambio de contraseña exige la contraseña actual.

## Redirección autenticada

- Con `dashboard.view`: Dashboard.
- Sin Dashboard pero con `agencies.view`: listado de Agencias.
- Sin ambos: Mi perfil.

## Blade y Livewire

`@can`, `Gate::authorize()` y `$this->authorize()` son la fuente de verdad. Ocultar un enlace no sustituye la autorización del servidor.

## Acciones masivas de agencias

| Acción | Permiso | Efecto |
|---|---|---|
| Activar seleccionadas | `agencies.manage_status` | Solo `under_review` → `active` |
| Eliminar seleccionadas | `agencies.delete` | Soft Delete hacia papelera |
| Restaurar seleccionadas | `agencies.restore` | Solo papelera y con prevalidación de identidad |
| Eliminar definitivamente | `agencies.delete` + `agencies.restore` | Solo papelera y confirmación `ELIMINAR` |

## Recuperación de desarrollo

Ejecutar `php artisan db:seed` recrea permisos, sincroniza exactamente los tres roles y asigna `super-admin` únicamente a la cuenta de desarrollo configurada.
