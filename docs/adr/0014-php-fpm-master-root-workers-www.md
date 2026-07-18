# 0014 - PHP-FPM master como root y workers como `www`

## Estado

Aprobado

## Contexto

El servicio `app` ejecuta PHP-FPM Alpine. El proyecto usa bind mounts y necesita que Laravel escriba en `bootstrap/cache` y `storage`.

## Problema

Si el proceso master de PHP-FPM se ejecuta como un usuario no privilegiado como `www`, puede fallar al abrir `error_log` en `/proc/self/fd/2` y no inicializar correctamente el pool.

## Alternativas consideradas

- Ejecutar PHP-FPM completo como `www`
- Ejecutar PHP-FPM master como root y workers como `www`
- Ejecutar PHP-FPM como root permanente sin limitar workers

## Decisión

Ejecutar el master process de PHP-FPM como root y configurar el pool para que los workers corran como `www`.

## Justificación

- PHP-FPM está diseñado para iniciar el master con privilegios suficientes y delegar workers a un usuario no privilegiado.
- Evita el error de apertura de `error_log` en `/proc/self/fd/2`.
- Mantiene un modelo seguro y compatible con los bind mounts del proyecto.
- Permite que `queue` y `scheduler` sigan ejecutando `artisan` como `www` sin depender de PHP-FPM.

## Consecuencias

- Positivas:
  - PHP-FPM inicia correctamente
  - workers aislados con usuario no privilegiado
  - compatibilidad con `www`
- Negativas:
  - el entrypoint debe distinguir entre `php-fpm` y comandos `artisan`
  - se requiere un pool explícito para `www`

## Referencias

- `docker/php/Dockerfile`
- `docker/php/entrypoint.sh`
- `docker/php/fpm/www.conf`
- `docker-compose.yml`

