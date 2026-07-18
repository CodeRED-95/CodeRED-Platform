# 0012 - Usuario `www` y estrategia de permisos para bind mounts

## Estado

Aprobado

## Contexto

El código se monta como bind mount desde el host. Laravel necesita escribir en `bootstrap/cache` y `storage`.

## Problema

El usuario final no debe ser `www-data`, y los permisos temporales con `777` no son aceptables como solución permanente.

## Alternativas consideradas

- Ejecutar como `www-data`
- Ejecutar como `www`
- Ejecutar como root permanentemente

## Decisión

Usar el usuario interno `www` con UID/GID 1000 y un entrypoint idempotente que corrige directorios escribibles al arranque.

## Justificación

- coincide con el usuario real del entorno
- evita depender de `777`
- mantiene permisos razonables sobre bind mounts
- permite que el proceso PHP final no corra como root

## Consecuencias

- Positivas:
  - permisos consistentes
  - menos problemas entre host y contenedor
- Negativas:
  - se requiere entrypoint adicional
  - el contenedor debe reconstruirse cuando cambie la política

## Referencias

- `docker/php/Dockerfile`
- `docker/php/entrypoint.sh`
- `docs/DOCKER.md`

