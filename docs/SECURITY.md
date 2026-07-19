# Seguridad

## Autenticación

- Web: guard `web`
- API: Sanctum preparado para futuras credenciales/token
- El login usa autenticación tradicional por sesión con `POST /login`, `@csrf` y `Auth::attempt()`; no depende de Livewire.
- La sesión se regenera después de autenticar y se invalida por completo al cerrar sesión o detectar una cuenta bloqueada.
- `EnsureUserIsActive` expulsa cuentas suspendidas o inactivas incluso si su sesión ya estaba abierta.
- `EnsurePasswordIsChanged` restringe la navegación hasta completar un cambio obligatorio de contraseña.

## Autorización

- Policies para `Agencies`
- Permisos granulares por módulo
- Laravel nativo con `Gate::before` y Policies
- No sobrescribir `User::can()`
- Helpers propios recomendados: `hasPermission()`, `hasRole()`, `hasAnyRole()` y `hasAllPermissions()`

## CSRF

La web usa protección CSRF estándar de Laravel.

## Rate limiting

La API usa limitación por IP/usuario en `AppServiceProvider`.

## Validaciones

- Form Requests
- reglas por campo
- enum validation donde aplica

## Protección SSRF

El importador solo permite:

- `https`
- `gist.githubusercontent.com`
- `raw.githubusercontent.com`

## Protección XSS

- Escape por Blade
- No renderizar contenido no confiable sin escape

## Protección SQL Injection

- Query Builder / Eloquent
- `whereRaw` restringido a patrones conocidos

## Buenas prácticas

- No guardar secretos en el repositorio
- No exponer trazas en producción
- Mantener la lógica de autorización en Gates y Policies, nunca en overrides de métodos del framework
- No volver a importar `alpinejs` ni llamar `Alpine.start()` cuando el layout ya usa `@livewireScripts`; duplicar Alpine rompe la hidratación y puede exponer credenciales en la URL al degradar los formularios Livewire.

## Agencies Shalom

- La importación por URL solo debe aceptar HTTPS y hosts permitidos.
- El panel administrativo debe permanecer protegido por permisos.
- Las acciones de traslado deben quedar auditadas.

## Usuarios

- No sobrescribir `User::can()`.
- Usar `Gate::before` solo para superadministrador, devolviendo `null` para el resto.
- El campo `status` es la fuente autoritativa para bloquear cuentas `suspended` o `inactive`; `is_active` se conserva como campo legado sincronizado.
- No registrar contraseñas, hashes, tokens ni `remember_token` en auditoría.
- Registrar los cambios de credenciales solo mediante el marcador `credentials`.
- Consultar actividad de Usuarios únicamente con `users.view_activity` e historial
  de Agencias únicamente con `agencies.view_history`.
- No permitir que un usuario se elimine o se suspenda a sí mismo.
- Proteger al último superadministrador activo como cuenta crítica.
