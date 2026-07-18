# 0024. Bootstrap automático en el entrypoint

**Estado:** Aceptado

## Contexto

Después de una instalación limpia, el proyecto requería ejecutar manualmente comandos de Artisan para que login, migraciones, seeders y cachés quedaran operativos.

## Problema

Un proyecto que requiere pasos manuales después de `docker compose up -d --build` no está completamente automatizado y puede dejar el entorno en un estado parcial o inconsistente.

## Alternativas consideradas

- Mantener la inicialización manual.
- Crear un script externo y depender de él siempre.
- Mover el bootstrap idempotente al entrypoint del contenedor.

## Decisión

El bootstrap automático se ejecuta desde `docker/php/entrypoint.sh`.

## Justificación

- Garantiza que el entorno quede listo al levantar los contenedores.
- Elimina pasos manuales para el usuario.
- Hace que migraciones, seeders y limpieza de caché formen parte del ciclo de arranque.
- Mantiene la lógica cerca del runtime real del contenedor.

## Consecuencias

- El entrypoint debe ser idempotente.
- Los seeders deben poder ejecutarse múltiples veces sin duplicar datos.
- Las cachés deben limpiarse automáticamente cuando corresponda.
- La documentación de instalación debe reflejar el flujo automático.

## Referencias

- [docker/php/entrypoint.sh](../../docker/php/entrypoint.sh)
- [docs/INSTALL.md](../INSTALL.md)
