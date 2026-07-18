# Variables de entorno

Todas las variables listadas provienen de `.env.example`.

## Aplicación

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `APP_NAME` | Nombre visible de la aplicación. | `CodeRED Platform` | `CodeRED Platform` | Sí | Cambia títulos y branding. | `VITE_APP_NAME` |
| `APP_ENV` | Entorno de ejecución. | `local` | `production` | Sí | Cambia comportamiento de errores y caché. | `APP_DEBUG` |
| `APP_KEY` | Clave criptográfica de Laravel. | Generada con `key:generate` | `base64:...` | Sí | Rompe cifrado y sesiones si cambia. | Ninguna |
| `APP_DEBUG` | Activa depuración. | `false` en producción | `true` | Sí | Expone errores detallados. | `LOG_LEVEL` |
| `APP_URL` | URL base de la aplicación. | URL pública real | `http://localhost:8080` | Sí | Afecta enlaces absolutos. | `SANCTUM_STATEFUL_DOMAINS` |
| `APP_TIMEZONE` | Zona horaria de la app. | `America/Lima` | `America/Lima` | Sí | Cambia fechas mostradas y tareas programadas. | `APP_LOCALE` |
| `APP_LOCALE` | Idioma principal. | `es` | `es` | Sí | Cambia traducciones. | `APP_FALLBACK_LOCALE` |
| `APP_FALLBACK_LOCALE` | Idioma de respaldo. | `es` | `es` | Sí | Se usa si falta traducción. | `APP_LOCALE` |
| `APP_FAKER_LOCALE` | Locale de Faker. | `es_PE` | `es_PE` | Sí | Afecta datos ficticios. | Factories |

## Logs

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `LOG_CHANNEL` | Canal principal de logs. | `stack` | `stack` | Sí | Cambia destino de logs. | `LOG_STACK`, `LOG_LEVEL` |
| `LOG_STACK` | Canal apilado. | `single` | `single` | Sí | Define el canal de salida. | `LOG_CHANNEL` |
| `LOG_LEVEL` | Nivel mínimo de registro. | `debug` en local, `error` en producción | `debug` | Sí | Más o menos detalle en logs. | `APP_DEBUG` |

## Base de datos

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `DB_CONNECTION` | Driver de base de datos. | `pgsql` | `pgsql` | Sí | Cambia el motor usado por Laravel. | `DB_HOST`, `DB_PORT` |
| `DB_HOST` | Host de PostgreSQL. | `postgres` en Docker | `postgres` | Sí | Rompe la conexión si no resuelve. | `DB_PORT` |
| `DB_PORT` | Puerto PostgreSQL. | `5432` | `5432` | Sí | Cambia el puerto de conexión. | `DB_HOST` |
| `DB_DATABASE` | Nombre de la base. | `codered` | `codered` | Sí | Apunta a otra base. | `DB_USERNAME` |
| `DB_USERNAME` | Usuario PostgreSQL. | `codered` | `codered` | Sí | Cambia permisos y acceso. | `DB_PASSWORD` |
| `DB_PASSWORD` | Contraseña PostgreSQL. | Definir en secreto seguro | `PENDIENTE DE CONFIGURAR` | Sí | Si cambia, deben coincidir credenciales. | `DB_USERNAME` |

## Cache

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `CACHE_STORE` | Driver de caché. | `redis` | `redis` | Sí | Cambia el backend de caché. | `REDIS_*` |

## Session

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `SESSION_DRIVER` | Driver de sesiones. | `redis` | `redis` | Sí | Afecta autenticación web. | `SESSION_LIFETIME`, `SESSION_DOMAIN` |
| `SESSION_LIFETIME` | Minutos de vida de sesión. | `120` | `120` | Sí | Sesiones más cortas o largas. | `SESSION_DRIVER` |
| `SESSION_DOMAIN` | Dominio de cookies de sesión. | `null` en local | `null` | No | Cambia alcance de cookie. | `SANCTUM_STATEFUL_DOMAINS` |

## Queue

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `QUEUE_CONNECTION` | Driver de colas. | `redis` | `redis` | Sí | Cambia el procesamiento asíncrono. | `REDIS_*` |

## Redis

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `REDIS_CLIENT` | Cliente Redis. | `phpredis` | `phpredis` | Sí | Si no está instalado, falla la conexión. | `REDIS_HOST`, `REDIS_PORT` |
| `REDIS_HOST` | Host Redis. | `redis` en Docker | `redis` | Sí | Rompe caché/colas/sesiones. | `REDIS_PORT` |
| `REDIS_PASSWORD` | Contraseña Redis. | `null` en local | `null` | No | Si se define, debe coincidir con el servidor. | `REDIS_HOST` |
| `REDIS_PORT` | Puerto Redis. | `6379` | `6379` | Sí | Cambia el puerto de conexión. | `REDIS_HOST` |

## Mail

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `BROADCAST_CONNECTION` | Driver de broadcast. | `log` | `log` | No | Cambia el canal de broadcast. | Ninguna |

## Archivos

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `FILESYSTEM_DISK` | Disco por defecto. | `local` | `local` | Sí | Cambia el almacenamiento de archivos. | Importador |

## Sanctum

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | Dominios que usan autenticación con cookies. | `localhost:8080,127.0.0.1:8080` | `localhost:8080,127.0.0.1:8080` | Sí | Si falta el dominio correcto, falla la sesión SPA. | `APP_URL`, `SESSION_DOMAIN` |

## Variables propias del proyecto

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `DEV_ADMIN_NAME` | Nombre del usuario administrador de desarrollo. | `Administrador Dev` | `Administrador Dev` | Sí | Cambia el seed del usuario inicial. | `DEV_ADMIN_EMAIL`, `DEV_ADMIN_PASSWORD` |
| `DEV_ADMIN_EMAIL` | Correo del usuario administrador de desarrollo. | `admin@codered.local` | `admin@codered.local` | Sí | Define el correo del usuario sembrado. | `DEV_ADMIN_PASSWORD` |
| `DEV_ADMIN_PASSWORD` | Contraseña del usuario administrador de desarrollo. | Cambiar en entornos reales | `ChangeMe123!` | Sí | Si es débil, compromete el seed inicial. | `DEV_ADMIN_EMAIL` |
| `VITE_APP_NAME` | Nombre visible en frontend. | `"CodeRED Platform"` | `"CodeRED Platform"` | Sí | Cambia el título del frontend. | `APP_NAME` |

## No utilizadas o parcialmente utilizadas

| Variable | Estado |
|---|---|
| `BROADCAST_CONNECTION` | Definida, uso actual mínimo en el proyecto. |

## Categorías no presentes todavía

- Mail real: `PENDIENTE DE CONFIGURAR`
- Importador específico vía URL configurable: `PENDIENTE DE CONFIGURAR`
