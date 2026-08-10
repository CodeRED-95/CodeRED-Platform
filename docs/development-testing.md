# Desarrollo y pruebas

## Requisitos

- Docker Desktop.
- Docker Compose.
- VS Code.
- Extensión Remote - SSH.
- Extensión Docker.
- Extensiones PHP recomendadas por el proyecto.

## Primer uso

1. Abre o clona el repositorio.
2. Abre VS Code y conéctate por Remote SSH.
3. Abre el repositorio `CodeRED-Platform`.
4. Levanta los servicios con `docker compose up -d`.
5. Verifica el entorno con `docker compose exec -T app php -v`, `docker compose exec -T app composer --version` y `docker compose exec -T app php artisan about`.
6. Ejecuta `docker compose exec -T app composer check`.

## Comandos

- `docker compose exec -T app composer test`
- `docker compose exec -T app composer test-unit`
- `docker compose exec -T app composer test-feature`
- `docker compose exec -T app composer lint`
- `docker compose exec -T app composer lint-fix`
- `docker compose exec -T app composer analyse`
- `docker compose exec -T app composer check`
- `docker compose exec -T app composer verify` (alias compatible de `composer check`)

## Tareas de VS Code

- Abre la paleta con `Ctrl + Shift + P`.
- Ejecuta `Tasks: Run Task`.
- La tarea predeterminada del grupo `test` es `PHP: Check completo`. Todas las tareas invocan `docker compose exec -T app` y se ejecutan sobre el servicio PHP `app`.

## Verificación desde el host

Linux y macOS:

```bash
./verify.sh
```

Windows PowerShell:

```powershell
./verify.ps1
```

Ambos scripts levantan las dependencias mínimas y ejecutan `composer check` mediante `docker compose exec -T app`. No requieren PHP ni Composer instalados en el host.

## Resolución de problemas

- Si Docker Desktop no está iniciado, arráncalo antes de abrir el contenedor.
- Si el servicio PHP no existe, revisa `docker-compose.yml`.
- Si faltan dependencias en `vendor`, ejecuta `composer install` dentro del contenedor.
- Si la base de pruebas no existe, el bootstrap de PHPUnit la crea de forma idempotente.
- Si PostgreSQL no responde, revisa `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD`.
- Si Redis no responde, revisa `REDIS_HOST` y `REDIS_PORT`.
- Si PHPStan usa demasiada memoria, ajusta `phpstan.neon.dist`.
- Si la ruta de trabajo es incorrecta, confirma que sea `/var/www/html`.
- Si las pruebas usan la base de desarrollo por error, revisa `phpunit.xml` y `.env.testing`.
