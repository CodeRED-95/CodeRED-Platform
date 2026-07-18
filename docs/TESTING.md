# Testing

## Cómo ejecutar pruebas

Dentro del Dev Container:

```bash
composer test
```

Desde el host:

```bash
docker compose exec -T app composer test
```

## Tipos de pruebas

- Feature
- Unit

## Cobertura actual

- API de health
- API pública de agencias
- Importador
- Traslados
- Login y autenticación
- Page object del Design System
- Manifest de Vite

## Datos de prueba

- Factories de `User`
- Factory de `Agency`
- Registros `TEST-` cuando se requieran manualmente

## Antes de hacer commit

1. Ejecutar pruebas.
2. Ejecutar Pint.
3. Ejecutar PHPStan; el resultado esperado es cero errores y no se usa baseline.
4. Verificar rutas.
5. Verificar migraciones si hubo cambios de esquema.

## Agencias Shalom

- `php artisan test` debe cubrir CRUD manual, búsqueda, filtros, validaciones, relaciones, importación, traslado, snapshot, API y páginas administrativas.
- Si cambia el formulario, probar persistencia real de creación/edición, normalización y errores de validación.
- Si cambia el listado, probar búsqueda, combinación de filtros, soft deletes y paginación.
- Si cambia el importador, validar normalización, duplicados y estrategia de conflicto.
- Agregar pruebas de superadmin, permisos, `Gate::before` y denegación 403.

## Login y Design System

- Verificar que `/login` responda 200.
- Verificar que el login sea un formulario Blade tradicional con `method="POST"`, `@csrf` y `action` hacia `login.store`.
- Verificar que el login no use Livewire, `wire:submit`, `wire:model`, `$wire` ni envío HTML duplicado.
- Verificar que el HTML final contenga `name="_token"` y campos `name="email"`, `name="password"` y `name="remember"`.
- Verificar que el login use mensajes en español para validación y credenciales inválidas.
- Verificar regeneración de sesión, intended URL, cookie de recordatorio, logout y CSRF.
- Verificar que cuentas suspendidas sean rechazadas y expulsadas si ya tenían sesión.
- Verificar el ciclo completo de cambio obligatorio de contraseña.
- Verificar que `/admin/design-system` cargue dentro del layout administrativo y no como HTML aislado.
- Verificar que el manifest de Vite existe y que los assets actuales referenciados por el manifest estén presentes.
- Verificar HTTP de assets con `docker compose exec app sh scripts/check-assets.sh`.
- Verificar en consola que no aparezca `Detected multiple instances of Alpine running`.

## VS Code y Dev Containers

- La integración oficial de desarrollo usa `.devcontainer/devcontainer.json`.
- La tarea predeterminada es `PHP: Check completo`.
- Las tareas de VS Code ejecutan comandos directos dentro del contenedor y no requieren `docker compose exec` cuando el proyecto ya está abierto en Dev Container.
- El comando de verificación principal es `composer check`; `composer verify` se conserva como alias.
- `verify.sh` y `verify.ps1` permiten lanzar la misma verificación desde el host sin instalar PHP ni Composer.
- Si la base de pruebas no existe, el bootstrap de PHPUnit la crea de forma idempotente.

## Usuarios

- Validar acceso HTTP 200 para superadministrador.
- Validar 403 para usuarios sin permiso.
- Validar creación de usuario y hash de contraseña.
- Validar estados `active`, `suspended` e `inactive`.
- Validar que un usuario suspendido no pueda iniciar sesión.
