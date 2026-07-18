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
