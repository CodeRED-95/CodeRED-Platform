# Redis

## Estrategia actual

CodeRED Platform usa Redis para caché, sesiones, colas y health checks.

## Cliente

La integración se hace con PhpRedis:

```env
REDIS_CLIENT=phpredis
```

## Redis sin contraseña

Si Redis no usa autenticación, las variables deben quedar vacías:

```env
REDIS_USERNAME=
REDIS_PASSWORD=
```

No usar:

```env
REDIS_PASSWORD=null
```

porque la cadena `null` puede terminar provocando `AUTH`.

## Redis con contraseña

Si Redis usa autenticación, Docker Compose y Laravel deben compartir la misma contraseña y, si aplica, el mismo usuario ACL.

## Bases lógicas

| Variable | Uso |
|---|---|
| `REDIS_DB` | Base por defecto |
| `REDIS_CACHE_DB` | Base para caché |

## Verificación

```bash
docker compose exec redis redis-cli ping
docker compose exec app php artisan health:redis
```

La respuesta esperada es:

```text
PONG
```

## Limpieza de cachés

Después de cambiar variables de Redis, limpiar la configuración de Laravel:

```bash
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear
```

## Problemas comunes

| Problema | Causa probable | Solución |
|---|---|---|
| `ERR AUTH <password> called without any password configured` | `REDIS_PASSWORD` no está vacío aunque Redis no autentica | Vaciar `REDIS_PASSWORD` y limpiar cachés |
| `Class "Redis" not found` | La extensión PhpRedis no está instalada | Reconstruir la imagen PHP con `redis` habilitado |
