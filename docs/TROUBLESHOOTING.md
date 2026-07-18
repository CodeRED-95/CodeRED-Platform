# Troubleshooting

## Docker

| Problema | Causa probable | Solución |
|---|---|---|
| `docker` no se reconoce | Docker no está instalado | Instalar Docker Desktop / Engine |
| `docker compose up` falla | Error de build | Revisar logs del servicio |

## Laravel

| Problema | Causa probable | Solución |
|---|---|---|
| `php artisan` no funciona | Contenedor `app` detenido | Levantar contenedores |
| `APP_KEY` vacío | No se generó clave | Ejecutar `key:generate` |
| Login muestra `Credenciales inválidas` después de una instalación limpia | El bootstrap no terminó o las cachés antiguas siguen activas | Reiniciar los contenedores; el entrypoint debe ejecutar migraciones, seeders y limpieza de caché automáticamente |
| PostgreSQL rechaza la contraseña | Las credenciales de Laravel no coinciden con el volumen ya inicializado | Sincronizar el rol dentro de PostgreSQL o recrear la base solo si el volumen está vacío |
| `bootstrap/cache` no es escribible | Permisos de bind mount o usuario incorrecto | Verificar entrypoint y propietario `www:www` |
| `storage/logs/laravel.log` no es escribible | Archivo o carpeta sin permisos | Verificar `storage/logs` con permisos `775` y `664` |
| `FPM initialization failed` | PHP-FPM master no está arrancando como root | Revisar `docker/php/entrypoint.sh` y `docker/php/fpm/www.conf` |

## Redis

| Problema | Causa probable | Solución |
|---|---|---|
| Sesiones no persisten | Redis inaccesible | Revisar `REDIS_HOST` |
| `Class "Redis" not found` | La extensión PhpRedis no está instalada | Reconstruir la imagen PHP con la extensión `redis` habilitada |
| `ERR AUTH <password> called without any password configured` | `REDIS_PASSWORD` tiene un valor no vacío aunque Redis no usa contraseña | Vaciar `REDIS_PASSWORD` y limpiar cachés de Laravel |

## PostgreSQL

| Problema | Causa probable | Solución |
|---|---|---|
| No conecta a DB | Credenciales incorrectas | Revisar `.env` |

## Migraciones

| Problema | Causa probable | Solución |
|---|---|---|
| Tabla existe | Migración corrida antes | Revisar estado con `migrate:status` |

## Colas

| Problema | Causa probable | Solución |
|---|---|---|
| Jobs no corren | Worker detenido | Revisar servicio `queue` |

## Livewire

| Problema | Causa probable | Solución |
|---|---|---|
| Vistas no cargan | Assets no compilados | Ejecutar `npm run build` |
| `ViteManifestNotFoundException` | `public/build/manifest.json` no existe | Ejecutar `npm run build` y verificar que el directorio `public/build/` se generó |
| El formulario hace GET en vez de `POST /livewire/update` | El HTML final no está siendo hidratado por Livewire o el botón `submit` está siendo sobrescrito | Verificar `wire:id`, `wire:submit.prevent`, `@livewireScripts` y el HTML final del botón |
| `validation.required` aparece visible | Faltan traducciones en `lang/es/validation.php` o el input no está propagando `wire:model` | Revisar el componente `Login`, los inputs Blade y la localización |
| El login muestra campos llenos pero Livewire los toma vacíos | El navegador autocompletó sin sincronizar el estado del componente | Confirmar `name`, `id`, `autocomplete`, `wire:model.live` y la sincronización al enviar |
| Aparece `Detected multiple instances of Alpine running` | Alpine se está inicializando manualmente en `resources/js/app.js` además de la instancia incluida por Livewire 3 | Eliminar `import Alpine from 'alpinejs'`, `window.Alpine = Alpine` y `Alpine.start()`; dejar que `@livewireScripts` cargue Alpine una sola vez |
| `Alpine Expression Error: $wire is not defined` | Se está usando `\$wire.set()` o `\$wire.call()` dentro de expresiones Alpine del formulario | Eliminar la sincronización Alpine duplicada y dejar que `wire:model` maneje el estado |
| `No composer.lock file present` | El lockfile no existe o no se persistió | Verificar que `composer.lock` exista en el host y dentro del contenedor |

## NPM

| Problema | Causa probable | Solución |
|---|---|---|
| `The npm ci command can only install with an existing package-lock.json` | No existe `package-lock.json` | Ejecutar `npm install` una vez para generarlo, versionarlo y luego usar `npm ci` |
| `EUSAGE` durante `npm ci` | `package-lock.json` ausente o desincronizado | Sincronizar `package.json` y `package-lock.json`, luego repetir `npm ci` |

## Importador

| Problema | Causa probable | Solución |
|---|---|---|
| URL rechazada | SSRF o host no permitido | Usar raw de GitHub Gist |
| Dotenv falla al leer `.env` | Valores con espacios sin comillas | Encerrar el valor entre comillas |

## GitHub Gist

- Host permitido: `gist.githubusercontent.com`
- Host permitido: `raw.githubusercontent.com`
- Esquema requerido: `https`

## Git

| Problema | Causa probable | Solución |
|---|---|---|
| `fatal: detected dubious ownership in repository at '/var/www/html'` | Git no considera seguro el directorio montado | Registrar `/var/www/html` como `safe.directory` en la imagen o el entrypoint. La solución actual lo hace automáticamente con `git config --global --add safe.directory /var/www/html`. |

## Composer

| Problema | Causa probable | Solución |
|---|---|---|
| `No composer.lock file present` | El lockfile no está presente en el árbol o no se persistió | Ejecutar `composer install`, generar `composer.lock` y versionarlo en el repositorio |

## Agencies Shalom

| Problema | Causa probable | Solución |
|---|---|---|
| El panel de agencias carga vacío | No existen registros o el filtro está restringiendo demasiado | Revisar filtros y seeders |
| El importador marca conflictos | Hay coincidencias por `source_reference` o código | Elegir estrategia adecuada |
| El detalle público no muestra traslado | La agencia no tiene `has_moved = true` o no se guardó el destino | Revisar la Action de traslado |
| `/admin/agencies` devuelve 403 | El usuario no tiene `agencies.view` o no fue asignado como `super-admin` | Ejecutar `php artisan db:seed` y revisar el rol del administrador |
| El superadministrador no entra | `RolesAndPermissionsSeeder` no se ejecutó o el usuario quedó sin rol | Re-seed con el orden correcto |
| `/admin/design-system` aparece como HTML nativo | La ruta no está resolviendo el componente Livewire `App\Livewire\Admin\DesignSystem` o no usa `layouts.app` | Verificar la ruta y el método `render()` |
