# 0011 - Uso de PhpRedis como cliente Redis

## Estado

Aprobado

## Contexto

La aplicación usa Redis para caché, colas y sesión. La configuración actual apunta a `REDIS_CLIENT=phpredis`.

## Problema

Laravel necesita un cliente Redis disponible dentro de la imagen PHP. Sin la extensión, aparece el error `Class "Redis" not found`.

## Alternativas consideradas

- Predis en PHP puro
- PhpRedis como extensión nativa

## Decisión

Usar PhpRedis como extensión PHP nativa dentro de la imagen Docker.

## Justificación

- mejor integración con Laravel
- rendimiento superior frente a clientes en PHP puro
- coincide con la configuración ya declarada en `.env`

## Consecuencias

- Positivas:
  - Redis disponible para caché, colas y sesiones
  - menor fricción con la configuración actual
- Negativas:
  - la imagen debe compilar la extensión

## Referencias

- `docker/php/Dockerfile`
- `config/cache.php`
- `config/queue.php`
- `config/session.php`

