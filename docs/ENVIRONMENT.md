# Variables de entorno

Todas las variables listadas provienen de `.env.example`.

## Aplicación

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `APP_NAME` | Nombre visible de la aplicación. Debe escribirse entre comillas si contiene espacios. | `"CodeRED Platform"` | `"CodeRED Platform"` | Sí | Cambia títulos y branding. Si contiene espacios y no va entre comillas, Dotenv falla al interpretar el archivo. | `VITE_APP_NAME` |
| `APP_ENV` | Entorno de ejecución. | `local` | `production` | Sí | Cambia comportamiento de errores y caché. | `APP_DEBUG` |
| `APP_KEY` | Clave criptográfica de Laravel. | Generada con `key:generate` | `base64:...` | Sí | Rompe cifrado y sesiones si cambia. | Ninguna |
| `APP_DEBUG` | Activa depuración. | `false` en producción | `true` | Sí | Expone errores detallados. | `LOG_LEVEL` |
| `APP_URL` | URL base de la aplicación. | URL pública real | `http://localhost:8090` | Sí | Afecta enlaces absolutos. Debe coincidir con el puerto expuesto por Nginx. | `SANCTUM_STATEFUL_DOMAINS` |
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
| `DB_DATABASE` | Nombre de la base. | `codered` | `codered` | Sí | Apunta a otra base. Es la fuente usada por Docker Compose para inicializar PostgreSQL. | `DB_USERNAME` |
| `DB_USERNAME` | Usuario PostgreSQL. | `codered` | `codered` | Sí | Cambia permisos y acceso. Es la fuente usada por Docker Compose para inicializar PostgreSQL. | `DB_PASSWORD` |
| `DB_PASSWORD` | Contraseña PostgreSQL. | Definir en secreto seguro | `PENDIENTE DE CONFIGURAR` | Sí | Si cambia, deben coincidir credenciales. Docker Compose la reutiliza para el servicio `postgres`. | `DB_USERNAME` |

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
| `REDIS_USERNAME` | Usuario Redis. | vacío en local | `PENDIENTE DE CONFIGURAR` | No | Si Redis usa ACL, debe coincidir con el usuario configurado. | `REDIS_PASSWORD` |
| `REDIS_PASSWORD` | Contraseña Redis. | vacío en local cuando Redis no autentica | `PENDIENTE DE CONFIGURAR` | No | Si se define, Laravel enviará AUTH. No escribir `null` como texto. | `REDIS_USERNAME` |
| `REDIS_PORT` | Puerto Redis. | `6379` | `6379` | Sí | Cambia el puerto de conexión. | `REDIS_HOST` |
| `REDIS_DB` | Base de datos Redis por defecto. | `0` | `0` | Sí | Cambia la base lógica por defecto. | `REDIS_CACHE_DB` |
| `REDIS_CACHE_DB` | Base de datos Redis para caché. | `1` | `1` | Sí | Cambia la base lógica de caché. | `REDIS_DB` |

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
| `SANCTUM_STATEFUL_DOMAINS` | Dominios que usan autenticación con cookies. | `localhost:8090,127.0.0.1:8090` | `localhost:8090,127.0.0.1:8090` | Sí | Si falta el dominio correcto, falla la sesión SPA. Para acceso por LAN puede agregarse la IP del host, por ejemplo `192.168.18.124:8090`. | `APP_URL`, `SESSION_DOMAIN` |

## Variables propias del proyecto

| Variable | Descripción | Recomendado | Ejemplo | Obligatoria | Consecuencias de cambiarla | Relacionadas |
|---|---|---|---|---|---|---|
| `DEV_ADMIN_NAME` | Nombre del usuario administrador de desarrollo. Debe escribirse entre comillas si contiene espacios. | `"Administrador Dev"` | `"Administrador Dev"` | Sí | Cambia el seed del usuario inicial. Si contiene espacios y no va entre comillas, Dotenv falla al interpretar el archivo. | `DEV_ADMIN_EMAIL`, `DEV_ADMIN_PASSWORD` |
| `DEV_ADMIN_EMAIL` | Correo del usuario administrador de desarrollo. | `admin@codered.local` | `admin@codered.local` | Sí | Define el correo del usuario sembrado. | `DEV_ADMIN_PASSWORD` |
| `DEV_ADMIN_PASSWORD` | Contraseña del usuario administrador de desarrollo. | Cambiar en entornos reales | `CHANGE_THIS_BEFORE_SEEDING` | Sí | Si es débil o es un valor de ejemplo, compromete el seed inicial. | `DEV_ADMIN_EMAIL` |
| `VITE_APP_NAME` | Nombre visible en frontend. | `"CodeRED Platform"` | `"CodeRED Platform"` | Sí | Cambia el título del frontend. | `APP_NAME` |

## No utilizadas o parcialmente utilizadas

| Variable | Estado |
|---|---|
| `BROADCAST_CONNECTION` | Definida, uso actual mínimo en el proyecto. |

## Categorías no presentes todavía

- Mail real: `PENDIENTE DE CONFIGURAR`
- Importador específico vía URL configurable: `PENDIENTE DE CONFIGURAR`
- `composer.lock`: debe existir y versionarse para instalaciones reproducibles.
- `REDIS_PASSWORD=null` no es equivalente a vacío: la cadena `null` puede provocar AUTH.

## Regla de sintaxis Dotenv

Todo valor con espacios, `#`, comillas o caracteres que puedan romper el parser debe escribirse entre comillas.

Ejemplos válidos:

```env
APP_NAME="CodeRED Platform"
DEV_ADMIN_NAME="Administrador Dev"
```

## Nota sobre PostgreSQL y volúmenes persistentes

Las variables `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` se usan para inicializar el servicio `postgres` en Docker Compose.
Si el volumen de PostgreSQL ya fue creado con otras credenciales, modificar `.env` no cambia automáticamente la contraseña interna del rol. En ese caso debe sincronizarse el rol dentro de PostgreSQL sin borrar el volumen.

Las variables `DEV_ADMIN_*` se leen exclusivamente desde `config/codered.php` durante el bootstrap normal. El seeder consume `config()` para seguir funcionando con la configuración cacheada.

## API v1

| Variable | Uso |
|---|---|
| `API_ENABLED` | Habilita configuración de API |
| `API_VERSION` | Versión informada |
| `API_DOCS_ENABLED` | Habilita documentación interna |
| `API_DOCS_REQUIRE_AUTH` | Reserva documental para autenticados |
| `API_RATE_LIMIT_PER_MINUTE` | Límite por token |
| `API_ALLOWED_ORIGINS` | Orígenes CORS explícitos separados por coma |
| `API_DEFAULT_TOKEN_EXPIRATION_DAYS` | Expiración propuesta en panel |
| `API_MAX_PER_PAGE` | Máximo de paginación |
| `API_AGENCY_SCHEMA_VERSION` | Versión independiente del contrato de catálogo (actual: 2) |
| `API_AGENCY_CHANGES_DEFAULT_LIMIT` | Tamaño predeterminado de una página incremental |
| `API_AGENCY_CHANGES_MAX_LIMIT` | Límite máximo incremental |
| `API_AGENCY_CHANGELOG_RETENTION_DAYS` | Retención del changelog append-only |
| `API_ETAG_ENABLED` | Activa ETag y If-None-Match |
| `API_LAST_MODIFIED_ENABLED` | Activa Last-Modified fiable |

En producción, `API_ALLOWED_ORIGINS` debe incluir únicamente dominios necesarios y el origen `chrome-extension://ID_DEFINITIVO`.

## Proxy inverso y Cloudflare Tunnel

Laravel confía en los encabezados `X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Port`, `X-Forwarded-Proto` y `X-Forwarded-Prefix` que Nginx reenvía desde el proxy frontal. Las interfaces del mismo origen deben usar rutas relativas; no se debe corregir HTTPS mediante `URL::forceScheme()` porque el acceso local continúa usando HTTP. El servicio PHP no debe exponerse directamente fuera de la red Docker.

## API DNI, PeruDevs y límites separados

| Variable | Uso |
|---|---|
| `AGENCY_API_RATE_LIMIT_PER_MINUTE` | Límite independiente por token para agencias |
| `DNI_API_RATE_LIMIT_PER_MINUTE` | Límite independiente por token para DNI |
| `DNI_PERUDEVS_ENABLED` | Habilita el respaldo externo; desactivado por defecto |
| `DNI_PERUDEVS_BASE_URL` | Endpoint GET completo de PeruDevs |
| `DNI_PERUDEVS_API_KEY` | API key de emergencia; la configuración cifrada en base de datos tiene prioridad |
| `DNI_PERUDEVS_TIMEOUT` | Timeout HTTP |
| `DNI_PERUDEVS_RETRIES` | Reintentos transitorios |
| `DNI_CACHE_TTL` | TTL de resultados exitosos |
| `DNI_NOT_FOUND_CACHE_TTL` | TTL de caché negativa |
| `DNI_PERSIST_EXTERNAL_RESULTS` | Persiste resultados externos normalizados |
| `DNI_REFRESH_AFTER_DAYS` | Antigüedad para refresco asíncrono |
| `DNI_LEGACY_DB_*` | Conexión opcional de solo lectura a `dni-api` |

La base de datos prevalece sobre `.env`. Nunca se versiona una API key real.

## RUC e importación del padrón

| Variable | Uso |
|---|---|
| `RUC_ENABLED` | Habilita las rutas de consulta RUC. |
| `RUC_CACHE_ENABLED` / `RUC_CACHE_TTL` | Controlan la caché Redis de consulta exacta. |
| `RUC_RATE_LIMIT_PER_MINUTE` | Límite independiente para `ruc:consultar`. |
| `RUC_SEARCH_RATE_LIMIT_PER_MINUTE` | Límite independiente para `ruc:buscar`. |
| `RUC_IMPORT_DISK` / `RUC_IMPORT_DIRECTORY` | Almacenamiento privado del TXT. |
| `RUC_IMPORT_MAX_SIZE_MB` | Tamaño máximo aceptado. |
| `RUC_IMPORT_SYNC_HASH_MAX_MB` | Umbral para calcular SHA-256 en HTTP; archivos mayores se preparan en cola (100 MB por defecto). |
| `RUC_IMPORT_ENCODING` / `RUC_IMPORT_DELIMITER` | Contrato de lectura del padrón. |
| `RUC_IMPORT_CHUNK_SIZE` | Tamaño de cada escritura idempotente. |
| `RUC_IMPORT_QUEUE` / `RUC_IMPORT_TIMEOUT` | Cola y tiempo máximo del worker. |
| `RUC_IMPORT_LOCK_SECONDS` | Exclusión distribuida entre importaciones. |
| `UBIGEO_SOURCE` | Fuente manual del catálogo; actualmente `alanube`. |
| `UBIGEO_ALANUBE_URL` | Página pública que contiene la tabla estructurada. |
| `UBIGEO_DOWNLOAD_TIMEOUT` / `UBIGEO_DOWNLOAD_RETRIES` | Límites del cliente HTTP de sincronización. |
| `UBIGEO_SYNC_ENABLED` | Habilita la sincronización manual; no provoca descargas al arrancar. |
| `RUC_SEARCH_MIN_LENGTH` / `RUC_SEARCH_MAX_RESULTS` | Protección de búsquedas parciales. |

La cola debe escuchar `ruc-imports` antes de `default`. Los padrones se almacenan fuera de `public/` y nunca deben versionarse.
