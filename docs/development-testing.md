# Desarrollo y pruebas

## Requisitos

- Docker Desktop.
- Docker Compose.
- VS Code.
- Extensión Dev Containers.
- Extensión Docker.
- Extensiones PHP recomendadas por el proyecto.

## Primer uso

1. Abre o clona el repositorio.
2. Abre VS Code.
3. Ejecuta `Dev Containers: Reopen in Container`.
4. Espera a que el servicio `app` termine de preparar el entorno.
5. Verifica `php`, `composer` y `php artisan`.
6. Ejecuta `composer check`.

## Comandos

- `composer test`
- `composer test-unit`
- `composer test-feature`
- `composer lint`
- `composer lint-fix`
- `composer analyse`
- `composer check`
- `composer verify` (alias compatible de `composer check`)

## Tareas de VS Code

- Abre la paleta con `Ctrl + Shift + P`.
- Ejecuta `Tasks: Run Task`.
- La tarea predeterminada del grupo `test` es `PHP: Check completo`. Todas las tareas usan `/var/www/html` y se ejecutan dentro del servicio PHP `app`.

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
- Si el Dev Container no abre, usa `Dev Containers: Rebuild and Reopen in Container`.
- Si la ruta de trabajo es incorrecta, confirma que sea `/var/www/html`.
- Si las pruebas usan la base de desarrollo por error, revisa `phpunit.xml` y `.env.testing`.
