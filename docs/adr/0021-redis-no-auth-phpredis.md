# 0021. Redis sin contraseña con PhpRedis

**Estado:** Aceptado

## Contexto

El entorno local usa Redis como contenedor sin autenticación. Laravel puede intentar autenticarse si `REDIS_PASSWORD` contiene un valor no vacío.

## Problema

La cadena `null` no equivale a vacío para la conexión Redis de Laravel. Si se define como texto, la aplicación puede enviar `AUTH` aunque el servidor Redis no tenga contraseña.

## Alternativas consideradas

- Dejar Redis sin contraseña y configurar Laravel con valores vacíos.
- Proteger Redis con contraseña y alinear Laravel y Docker Compose.
- Usar `null` como string especial.

## Decisión

Redis se mantiene sin autenticación en local y Laravel debe usar `REDIS_USERNAME=` y `REDIS_PASSWORD=` vacíos.

## Justificación

- El contenedor Redis no requiere autenticación en este entorno.
- Se elimina el error `ERR AUTH`.
- La configuración resulta más simple y menos propensa a errores.

## Consecuencias

- `config/database.php` debe leer explícitamente `REDIS_USERNAME`, `REDIS_PASSWORD`, `REDIS_DB` y `REDIS_CACHE_DB`.
- La documentación debe advertir que `null` como texto no es lo mismo que un valor vacío.
- Tras cualquier cambio de Redis, deben limpiarse las cachés de Laravel.

## Referencias

- [config/database.php](../../config/database.php)
- [docs/REDIS.md](../REDIS.md)
