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

## Redis

| Problema | Causa probable | Solución |
|---|---|---|
| Sesiones no persisten | Redis inaccesible | Revisar `REDIS_HOST` |

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

## GitHub Gist

- Host permitido: `gist.githubusercontent.com`
- Host permitido: `raw.githubusercontent.com`
- Esquema requerido: `https`

