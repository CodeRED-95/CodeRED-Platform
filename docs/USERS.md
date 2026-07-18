# Usuarios

## Resumen

El módulo de usuarios administra cuentas internas del panel de CodeRED Platform.

## Estados

| Estado | Etiqueta |
|---|---|
| `active` | Activo |
| `suspended` | Suspendido |
| `inactive` | Inactivo |

## Reglas críticas

- Un usuario no puede eliminarse a sí mismo.
- Un usuario no puede suspenderse a sí mismo.
- No se puede desactivar al último superadministrador activo.
- No se puede eliminar al último superadministrador.
- Solo un superadministrador puede gestionar otros superadministradores.
- `status` es la fuente autoritativa de acceso; una cuenta suspendida no puede entrar aunque `is_active` tenga un valor legado inconsistente.
- Una cuenta bloqueada durante una sesión activa es desconectada por middleware.
- `must_change_password` impide acceder al resto de páginas hasta persistir una contraseña nueva válida.

## Campos principales

| Campo | Descripción |
|---|---|
| `name` | Nombre visible |
| `email` | Correo de acceso |
| `status` | Estado administrativo |
| `must_change_password` | Obliga a cambiar contraseña al iniciar sesión |
| `last_login_at` | Último acceso |
| `last_login_ip` | IP del último acceso |

## Roles y permisos

| Permiso | Uso |
|---|---|
| `users.view` | Ver listado y detalle |
| `users.create` | Crear usuarios |
| `users.update` | Editar usuarios |
| `users.delete` | Eliminar usuarios |
| `users.restore` | Restaurar usuarios |
| `users.manage_roles` | Asignar roles |
| `users.reset_password` | Restablecer contraseñas |
| `users.manage_status` | Suspender o activar cuentas |
| `users.view_activity` | Ver IP y actividad sensible |
