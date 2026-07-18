# Autorización

## Principio

Laravel ya implementa la autorización nativa mediante `can()`, `cannot()`, `Gate`, Policies y middleware.  
En CodeRED Platform no debe sobrescribirse `User::can()`.

## Estrategia actual

| Capa | Uso |
|---|---|
| `User` | Helpers explícitos como `hasPermission()`, `hasRole()`, `hasAnyRole()` y `hasAllPermissions()` |
| `Gate::before` | Resolver superadministrador y traducir abilities de Policies a permisos almacenados en PostgreSQL |
| Policies | Autorizar acciones específicas por modelo |

## Flujo

1. Laravel llama a `can()`.
2. `Gate::before` revisa si el usuario es `super-admin` o si tiene el permiso requerido.
3. Las abilities como `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `import`, `export`, `viewHistory` y `manageStatus` se traducen a permisos del módulo `Agencies` cuando corresponde.
4. Si devuelve `true`, la acción se autoriza.
5. Si devuelve `null`, Laravel continúa con Policies o Gates definidos.
6. Si no hay coincidencia, se niega la acción.

## Qué no hacer

- No sobrescribir `User::can()`.
- No alterar la firma de métodos internos de `Authenticatable`.
- No duplicar lógica de permisos en Blade, Livewire o controladores.

## Helpers propios

```php
$user->hasPermission('agencies.view');
$user->hasRole('admin');
$user->hasAnyRole(['admin', 'super-admin']);
$user->hasAllPermissions(['agencies.view', 'agencies.create']);
```

## Políticas

Las Policies deben delegar en `hasPermission()` o en la regla de negocio específica del modelo.

### `AgencyPolicy`

| Ability | Permiso |
|---|---|
| `viewAny` | `agencies.view` |
| `view` | `agencies.view` |
| `create` | `agencies.create` |
| `update` | `agencies.update` |
| `delete` | `agencies.delete` |
| `restore` | `agencies.restore` |
| `import` | `agencies.import` |
| `export` | `agencies.export` |
| `viewHistory` | `agencies.view_history` |
| `manageStatus` | `agencies.manage_status` |

## Recuperación de acceso

Si el administrador de desarrollo pierde el rol o permisos:

1. Ejecutar `php artisan db:seed`.
2. Verificar que `RolesAndPermissionsSeeder` y `AdminSeeder` se ejecuten en ese orden.
3. Confirmar que el usuario definido en `.env` reciba el rol `super-admin`.

## Blade y Livewire

Blade y Livewire deben seguir usando la API nativa:

```php
@can('agencies.view')
    ...
@endcan

$this->authorize('update', $agency);
```

## Pruebas recomendadas

- Helpers de `User`
- `Gate::before`
- Policies del módulo
- Bloques Blade `@can`
- Acciones Livewire con `authorize()`
