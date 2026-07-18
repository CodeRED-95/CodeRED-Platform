# 0015 - Git Safe Directory para `/var/www/html`

## Estado

Aprobado

## Contexto

El proyecto se monta como bind mount dentro de los contenedores Docker. Git puede detectar el directorio como de propiedad dudosa dentro del contenedor y bloquear operaciones como `composer install`.

## Problema

Composer y otros flujos que invocan Git pueden fallar con:

```text
fatal: detected dubious ownership in repository at '/var/www/html'
```

## Alternativas consideradas

- Pedir al usuario ejecutar `git config --global --add safe.directory /var/www/html` manualmente
- Configurar `safe.directory` en la imagen Docker
- Desactivar la protección de Git globalmente

## Decisión

Registrar `/var/www/html` como `safe.directory` automáticamente durante el build y también reforzarlo en el entrypoint.

## Justificación

- evita intervención manual
- resuelve el problema de forma reproducible
- no desactiva una protección de Git a nivel global
- funciona bien con bind mounts

## Consecuencias

- Positivas:
  - Composer puede operar sin fallos por propiedad dudosa
  - mejor experiencia de instalación en contenedores
- Negativas:
  - la imagen debe reconstruirse si cambia la ruta del proyecto

## Referencias

- `docker/php/Dockerfile`
- `docker/php/entrypoint.sh`
- `docs/TROUBLESHOOTING.md`

