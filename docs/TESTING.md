# Testing

## Cómo ejecutar pruebas

```bash
docker compose exec app php artisan test
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
3. Ejecutar PHPStan.
4. Verificar rutas.
5. Verificar migraciones si hubo cambios de esquema.

## Agencias Shalom

- `php artisan test` debe cubrir importación, traslado, snapshot, API y páginas administrativas.
- Si cambia el formulario o la vista pública, agregar pruebas de render o de ruta.
- Si cambia el importador, validar normalización, duplicados y estrategia de conflicto.
- Agregar pruebas de superadmin, permisos, `Gate::before` y denegación 403.

## Login y Design System

- Verificar que `/login` responda 200.
- Verificar que el login sea un formulario Blade tradicional con `method="POST"`, `@csrf` y `action` hacia `login.store`.
- Verificar que el login no use Livewire, `wire:submit`, `wire:model`, `$wire` ni envío HTML duplicado.
- Verificar que el HTML final contenga `name="_token"` y campos `name="email"`, `name="password"` y `name="remember"`.
- Verificar que el login use mensajes en español para validación y credenciales inválidas.
- Verificar que `/admin/design-system` cargue dentro del layout administrativo y no como HTML aislado.
- Verificar que el manifest de Vite existe y que los assets actuales referenciados por el manifest estén presentes.
- Verificar HTTP de assets con `docker compose exec app sh scripts/check-assets.sh`.
- Verificar en consola que no aparezca `Detected multiple instances of Alpine running`.

## VS Code y Dev Containers

- La integración oficial de desarrollo usa `.devcontainer/devcontainer.json`.
- La tarea predeterminada de pruebas es `PHP: Todas las pruebas`.
- Las tareas de VS Code ejecutan comandos directos dentro del contenedor y no requieren `docker compose exec` cuando el proyecto ya está abierto en Dev Container.
- El comando de verificación principal es `composer verify`.
- Si la base de pruebas no existe, el bootstrap de PHPUnit la crea de forma idempotente.

## Usuarios

- Validar acceso HTTP 200 para superadministrador.
- Validar 403 para usuarios sin permiso.
- Validar creación de usuario y hash de contraseña.
- Validar estados `active`, `suspended` e `inactive`.
- Validar que un usuario suspendido no pueda iniciar sesión.
