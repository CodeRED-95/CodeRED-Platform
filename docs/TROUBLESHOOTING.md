# Troubleshooting

## Docker

| Problema | Causa probable | Solución |
|---|---|---|
| `docker` no se reconoce | Docker no está instalado | Instalar Docker Desktop / Engine |
| `docker compose up` falla | Error de build | Revisar logs del servicio |

## Laravel

| Problema | Causa probable | Solución |
|---|---|---|
| `php artisan` no funciona | Contenedor `app` detenido | Levantar contenedores |
| `APP_KEY` vacío | No se generó clave | Ejecutar `key:generate` |
| `bootstrap/cache` no es escribible | Permisos de bind mount o usuario incorrecto | Verificar entrypoint y propietario `www:www` |
| `storage/logs/laravel.log` no es escribible | Archivo o carpeta sin permisos | Verificar `storage/logs` con permisos `775` y `664` |
| `FPM initialization failed` | PHP-FPM master no está arrancando como root | Revisar `docker/php/entrypoint.sh` y `docker/php/fpm/www.conf` |

## Redis

| Problema | Causa probable | Solución |
|---|---|---|
| Sesiones no persisten | Redis inaccesible | Revisar `REDIS_HOST` |
| `Class "Redis" not found` | La extensión PhpRedis no está instalada | Reconstruir la imagen PHP con la extensión `redis` habilitada |

## PostgreSQL

| Problema | Causa probable | Solución |
|---|---|---|
| No conecta a DB | Credenciales incorrectas | Revisar `.env` |

## Migraciones

| Problema | Causa probable | Solución |
|---|---|---|
| Tabla existe | Migración corrida antes | Revisar estado con `migrate:status` |

## Colas

| Problema | Causa probable | Solución |
|---|---|---|
| Jobs no corren | Worker detenido | Revisar servicio `queue` |

## Livewire

| Problema | Causa probable | Solución |
|---|---|---|
| Vistas no cargan | Assets no compilados | Ejecutar `npm run build` |

## Importador

| Problema | Causa probable | Solución |
|---|---|---|
| URL rechazada | SSRF o host no permitido | Usar raw de GitHub Gist |
| Dotenv falla al leer `.env` | Valores con espacios sin comillas | Encerrar el valor entre comillas |

## GitHub Gist

- Host permitido: `gist.githubusercontent.com`
- Host permitido: `raw.githubusercontent.com`
- Esquema requerido: `https`

## Git

| Problema | Causa probable | Solución |
|---|---|---|
| `fatal: detected dubious ownership in repository at '/var/www/html'` | Git no considera seguro el directorio montado | Registrar `/var/www/html` como `safe.directory` en la imagen o el entrypoint |
