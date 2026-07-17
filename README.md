# CodeRED Platform

Base técnica inicial de una plataforma modular Laravel para administrar y consultar agencias de Shalom y futuros módulos.

## Stack

- Laravel 12.x
- PHP 8.2+
- PostgreSQL 16
- Redis 7
- Nginx 1.27
- Livewire 3
- TailwindCSS 3
- AlpineJS 3
- Laravel Sanctum 4

## Arquitectura

La estructura está preparada para crecer por dominios:

- `app/Core`
- `app/Modules`
- `app/Modules/Agencies`

La primera fase solo deja la infraestructura transversal y el módulo base de administración.

## Requisitos

- Docker
- Docker Compose
- Node.js 20+

## Desarrollo

1. Copia `.env.example` a `.env`.
2. Ajusta credenciales si lo necesitas.
3. Levanta los contenedores:

```bash
docker compose up -d --build
```

4. Instala dependencias dentro del contenedor si corresponde:

```bash
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app php artisan key:generate
```

5. Ejecuta migraciones y seeders:

```bash
docker compose exec app php artisan migrate --seed
```

6. Compila assets:

```bash
docker compose exec app npm run build
```

## Producción

- Usar secretos reales fuera del repositorio.
- Desactivar `APP_DEBUG`.
- Configurar HTTPS delante de Nginx.
- Mantener `SESSION_DRIVER=redis` y `QUEUE_CONNECTION=redis`.

## Comandos útiles

- `php artisan migrate`
- `php artisan db:seed`
- `php artisan test`
- `php artisan schedule:run`
- `php artisan queue:work`
- `vendor/bin/pint`
- `vendor/bin/phpstan analyse`

## Estructura

- `app/Core`: utilidades transversales.
- `app/Modules`: módulos de negocio.
- `routes/api.php`: API versionada.
- `routes/web.php`: interfaz web.
- `database/migrations`: esquema base.
- `database/seeders`: datos iniciales.

## Health check

`GET /api/v1/health`

Respuesta esperada:

- estado general
- conexión a PostgreSQL
- conexión a Redis
- versión de la app
- fecha y hora del servidor
