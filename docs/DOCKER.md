# Docker

## docker-compose

Servicios actuales:

| Servicio | Imagen / Build | Puerto | Propósito |
|---|---|---:|---|
| `app` | `docker/php/Dockerfile` | Ninguno expuesto directamente | PHP-FPM y Laravel |
| `nginx` | `nginx:1.27-alpine` | `8090:80` | Servidor web |
| `postgres` | `postgres:16-alpine` | Ninguno expuesto al host en compose actual | Base de datos |
| `redis` | `redis:7-alpine` | Ninguno expuesto al host en compose actual | Caché y colas |
| `queue` | `docker/php/Dockerfile` | Ninguno | Worker de colas |
| `scheduler` | `docker/php/Dockerfile` | Ninguno | Scheduler de Laravel |

## Volúmenes

| Volumen | Uso |
|---|---|
| `pgdata` | Persistencia de PostgreSQL |
| `redisdata` | Persistencia de Redis |

## Usuario de ejecución

El proyecto ejecuta PHP con el usuario interno `www`:

| Usuario | UID | GID |
|---|---:|---:|
| `www` | `1000` | `1000` |

`www-data` no se usa como usuario de ejecución final.

## Git Safe Directory

El contenedor registra automáticamente `/var/www/html` como directorio seguro de Git durante el build y el arranque para evitar el error:

```text
fatal: detected dubious ownership in repository at '/var/www/html'
```

La corrección se aplica sin intervención manual mediante:

```bash
git config --global --add safe.directory /var/www/html
```

## PHP-FPM

El contenedor `app` inicia PHP-FPM con el proceso master como root y el pool `www` definido en:

- `docker/php/fpm/www.conf`

Los workers de PHP-FPM corren como `www`, pero el master conserva el contexto necesario para abrir `error_log` y postprocesar la configuración.

Queue y scheduler no ejecutan FPM. Sus comandos se bajan de privilegios a `www` solo para correr `artisan`.

## Permisos

Los directorios escribibles se corrigen en el entrypoint de forma idempotente:

- `bootstrap/cache`
- `storage/framework/cache`
- `storage/framework/sessions`
- `storage/framework/views`
- `storage/logs`

Permisos objetivo:

- directorios: `775`
- archivos: `664`

## Redes

La red de Docker Compose es la generada por defecto por Compose.

## Iniciar

```bash
docker compose up -d --build
```

## Detener

```bash
docker compose down
```

## Reconstruir

```bash
docker compose up -d --build
```

## Actualizar

```bash
docker compose pull
docker compose up -d --build
```

## Entrar a contenedores

| Servicio | Comando |
|---|---|
| `app` | `docker compose exec app sh` |
| `queue` | `docker compose exec queue sh` |
| `scheduler` | `docker compose exec scheduler sh` |
| `postgres` | `docker compose exec postgres sh` |
| `redis` | `docker compose exec redis sh` |
| `nginx` | `docker compose exec nginx sh` |

## Revisar logs

```bash
docker compose logs -f app
docker compose logs -f nginx
docker compose logs -f postgres
docker compose logs -f redis
docker compose logs -f queue
docker compose logs -f scheduler
```

## Backup

PostgreSQL:

```bash
docker compose exec postgres pg_dump -U codered codered > backup.sql
```

Redis:

```bash
docker compose exec redis redis-cli SAVE
```

## Restaurar

PostgreSQL:

```bash
docker compose exec -T postgres psql -U codered -d codered < backup.sql
```

Redis:

```bash
PENDIENTE DE CONFIGURAR
```

## Comprobación de Redis y permisos

```bash
docker compose exec app php -m | grep -i redis
docker compose exec app php --ri redis
docker compose exec app php artisan health:redis
```

## Verificación de PHP-FPM

```bash
docker compose exec app php -v
docker compose exec app php -m
docker compose exec app php --ri redis
docker compose exec app php-fpm -tt
```

## Imagen PHP compartida

`app`, `queue` y `scheduler` reutilizan la misma imagen `docker/php/Dockerfile`. No se duplican imágenes específicas por servicio.

## Dev Container

`.devcontainer/devcontainer.json` reutiliza el servicio `app`; no crea una imagen PHP paralela. VS Code abre `/var/www/html` como usuario `www`, inicia los seis servicios del compose y conserva la publicación `8090:80` declarada por Nginx.

Las tareas del editor ejecutan PHP, Composer, Artisan y npm directamente dentro del contenedor. Los scripts `verify.sh` y `verify.ps1` son wrappers de host que delegan `composer check` a `docker compose exec -T app`.
