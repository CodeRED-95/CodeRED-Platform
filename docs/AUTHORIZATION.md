# Autorización

## Principio

Laravel ya implementa la autorización nativa mediante `can()`, `cannot()`, `Gate`, Policies y middleware.  
En CodeRED Platform no debe sobrescribirse `User::can()`.

## Estrategia actual

| Capa | Uso |
|---|---|
| `User` | Helpers explícitos como `hasPermission()`, `hasRole()`, `hasAnyRole()` y `hasAllPermissions()` |
| `Gate::before` | Resolver permisos almacenados en PostgreSQL |
| Policies | Autorizar acciones específicas por modelo |

## Flujo

1. Laravel llama a `can()`.
2. `Gate::before` revisa si el usuario tiene el permiso requerido.
3. Si devuelve `true`, la acción se autoriza.
4. Si devuelve `null`, Laravel continúa con Policies o Gates definidos.
5. Si no hay coincidencia, se niega la acción.

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
