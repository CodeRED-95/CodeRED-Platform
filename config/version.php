<?php

use App\Support\Version;

/*
|--------------------------------------------------------------------------
| Versión de la aplicación
|--------------------------------------------------------------------------
|
| La versión NO se define aquí ni en `.env`: la fuente única de verdad es
| `composer.json > extra.version`, y `App\Support\Version` la lee. Para
| cambiarla use `php artisan app:bump-version {major|minor|patch}`.
|
| `APP_VERSION` ya no se consulta. Se eliminó a propósito: una copia
| desactualizada en el `.env` de un servidor ganaba sobre el código y hacía
| que la aplicación reportara una versión que no era la desplegada.
|
*/

return [
    'current' => Version::current(),
    'source' => Version::sourcePath(),
    'api' => env('API_VERSION', 'v1'),
];
