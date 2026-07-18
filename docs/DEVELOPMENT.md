# Desarrollo

## Cómo crear módulos

- Crear carpeta en `app/Modules/<Modulo>`.
- Agregar `Models`, `Services`, `Actions`, `Resources`, `Enums`, `Support`.

## Factories en módulos

Cuando un modelo modular usa `HasFactory`, Laravel puede intentar inferir una factory dentro de un namespace incorrecto.

Regla del proyecto:

- si el modelo vive en `app/Modules/...` y la factory se mantiene en `database/factories`, el modelo debe declarar `newFactory()`;
- la factory debe importar explícitamente el modelo y declarar `protected $model = ...`;
- no depender de la inferencia automática del framework en módulos.

Ejemplo:

```php
protected static function newFactory(): Factory
{
    return AgencyFactory::new();
}
```

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
4. Si el permiso debe resolver `can()`, hacerlo mediante `Gate::before`, nunca sobrescribiendo `User::can()`.

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

## Autorización

- No sobrescribir métodos internos de `Authenticatable`.
- Usar `User::hasPermission()` y `User::hasRole()` como helpers explícitos.
- Delegar la autorización en Gates y Policies.

## Flujo frontend

| Escenario | Comando |
|---|---|
| Primera instalación | `npm install` |
| Instalaciones posteriores | `npm ci` |
| Generar assets para producción o despliegue | `npm run build` |
| Desarrollo con recarga en caliente | `npm run dev` |

`package-lock.json` debe versionarse junto con `package.json` para que `npm ci` funcione de forma reproducible.

## Seeders

- Mantener seeders pequeños y especializados.
- `DatabaseSeeder` debe orquestar, no contener toda la lógica.
- Separar roles, permisos, administrador, settings y datos demo cuando sea posible.
- Usar `updateOrCreate()` para datos que deben poder ejecutarse varias veces sin duplicar.

## Bootstrap automático

- El arranque inicial del proyecto vive en `docker/php/entrypoint.sh`.
- No mover al usuario a pasos manuales de `config:clear`, `optimize:clear`, `migrate`, `db:seed`, `storage:link` o `key:generate` si el bootstrap automático ya los resuelve.
- Cualquier cambio de flujo debe mantener la inicialización idempotente.
