# Desarrollo

## Cómo crear módulos

- Crear carpeta en `app/Modules/<Modulo>`.
- Agregar `Models`, `Services`, `Actions`, `Resources`, `Enums`, `Support`.

## Cómo agregar migraciones

```bash
docker compose exec app php artisan make:migration nombre
```

## Cómo agregar endpoints

- Registrar rutas en `routes/api.php` o `routes/web.php`.
- Usar controladores versionados en `App\Http\Controllers\Api\V1`.

## Cómo agregar permisos

1. Crear permiso en seeders o migraciones de datos.
2. Asociarlo al rol correspondiente.
3. Proteger la acción vía Policy.

## Buenas prácticas

- Usar Actions para operaciones de negocio.
- Usar Form Requests para validación.
- Evitar repositorios innecesarios.
- Mantener nombres consistentes con el dominio.

## Convenciones

- Español en interfaz y documentación.
- Inglés en nombres técnicos estables cuando ya existen en Laravel.
- Modularidad por dominio.

## Estructura de carpetas

- `app/Core`
- `app/Modules`
- `database/migrations`
- `database/seeders`
- `resources/views`

## Estilo de código

- PHP estándar del proyecto.
- Formateo con Pint.
- Análisis estático con PHPStan.

