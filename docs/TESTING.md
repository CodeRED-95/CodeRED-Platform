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
- Verificar que el login renderice `wire:model.live` en los inputs reales.
- Verificar que el login use mensajes en español para validación y credenciales inválidas.
- Verificar que `/admin/design-system` cargue dentro del layout administrativo y no como HTML aislado.
- Verificar que el manifest de Vite existe y que los assets actuales referenciados por el manifest estén presentes.
- Verificar HTTP de assets con `docker compose exec app sh scripts/check-assets.sh`.

## Usuarios

- Validar acceso HTTP 200 para superadministrador.
- Validar 403 para usuarios sin permiso.
- Validar creación de usuario y hash de contraseña.
- Validar estados `active`, `suspended` e `inactive`.
- Validar que un usuario suspendido no pueda iniciar sesión.
