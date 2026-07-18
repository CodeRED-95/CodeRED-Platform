# Docker

## docker-compose

Servicios actuales:

| Servicio | Imagen / Build | Puerto | Propósito |
|---|---|---:|---|
| `app` | `docker/php/Dockerfile` | Ninguno expuesto directamente | PHP-FPM y Laravel |
| `nginx` | `nginx:1.27-alpine` | `8080:80` | Servidor web |
| `postgres` | `postgres:16-alpine` | Ninguno expuesto al host en compose actual | Base de datos |
| `redis` | `redis:7-alpine` | Ninguno expuesto al host en compose actual | Caché y colas |
| `queue` | `docker/php/Dockerfile` | Ninguno | Worker de colas |
| `scheduler` | `docker/php/Dockerfile` | Ninguno | Scheduler de Laravel |

## Volúmenes

| Volumen | Uso |
|---|---|
| `pgdata` | Persistencia de PostgreSQL |
| `redisdata` | Persistencia de Redis |

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
