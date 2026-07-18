# 0018. Credenciales de administrador de desarrollo desde `.env`

**Estado:** Aceptado

## Contexto

El proyecto necesita un usuario inicial para desarrollo y pruebas del panel administrativo.

## Problema

No es seguro ni mantenible dejar credenciales fijas o valores de ejemplo silenciosos en seeders o documentación.

## Alternativas consideradas

- Hardcodear credenciales en el seeder.
- Pedir al usuario que escriba credenciales interactivamente.
- Leer credenciales desde variables de entorno y validarlas antes de sembrar.

## Decisión

El seeder lee `DEV_ADMIN_NAME`, `DEV_ADMIN_EMAIL` y `DEV_ADMIN_PASSWORD` desde `.env` y valida que no estén vacías.

## Justificación

- Evita exponer credenciales en el código.
- Hace el seeder idempotente.
- Permite cambiar credenciales sin editar PHP.

## Consecuencias

- `.env.example` debe usar valores ficticios y seguros.
- El seeder debe fallar de forma explícita si faltan variables obligatorias.

## Referencias

- [database/seeders/DatabaseSeeder.php](../../database/seeders/DatabaseSeeder.php)
- [docs/ENVIRONMENT.md](../ENVIRONMENT.md)
