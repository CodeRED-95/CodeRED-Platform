# Database y Cache de CodeRED Anime

Estado: fase 5 de CodeRED Anime.

## Objetivo

La Fase 5 agrega persistencia local para catalogo, relaciones externas,
temporadas, episodios, servidores, metadata normalizada y snapshots de cache de
providers.

Estas tablas no descargan ni almacenan contenido protegido. Guardan metadata,
referencias, estado operativo y snapshots JSON no sensibles.

## Tablas

- `anime`: catalogo canonico local.
- `anime_external_ids`: relacion entre anime local y providers externos.
- `seasons`: temporadas normalizadas.
- `episodes`: episodios normalizados.
- `episode_servers`: servidores detectados por episodio.
- `anime_metadata`: metadata enriquecida por proveedor, principalmente AniList.
- `provider_cache`: snapshots operativos de cache de providers.

## Cache

`ProviderCacheRepository` centraliza el cache de providers.

El flujo es:

```text
Provider
  -> ProviderCacheRepository
  -> Cache store configurado
  -> Snapshot opcional en provider_cache
```

El cache store recomendado es Redis:

```env
ANIME_CACHE_STORE=redis
```

En pruebas se puede usar `array` para evitar dependencias externas.

## Snapshots en Base de Datos

`provider_cache` guarda una representacion JSON normalizada de la respuesta
para observabilidad y depuracion. No se usa para rehidratar DTOs directamente,
porque los providers deben mantener tipos estables (`Anime`, `Episode`,
`Server`, `Stream`) aunque cambie la estrategia de persistencia.

## Variables

```env
ANIME_CACHE_ENABLED=true
ANIME_CACHE_STORE=redis
ANIME_CACHE_MIRROR_DATABASE=true
ANIME_CACHE_SEARCH_TTL=3600
ANIME_CACHE_METADATA_TTL=86400
ANIME_CACHE_EPISODES_TTL=3600
ANIME_CACHE_SERVERS_TTL=300
```

## Seguridad

- No se almacenan cookies.
- No se almacenan headers de autorizacion.
- No se almacenan tokens CSRF.
- No se guardan URLs introducidas por usuarios arbitrarios.
- Las URLs externas siguen controladas por allowlists en providers.

## Migracion

La migracion de fase 5 es aditiva. Crea tablas nuevas y no modifica ni borra
tablas existentes.

Comando de despliegue:

```bash
docker compose exec -T app php artisan migrate --force
```
