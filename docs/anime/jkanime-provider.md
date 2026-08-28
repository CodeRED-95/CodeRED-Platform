# JkAnime Provider

Estado: fase 3 de CodeRED Anime.

`JkAnimeProvider` encapsula toda la comunicacion con JkAnime. Ningun controller,
UI o integracion Jellyfin debe construir URLs de JkAnime directamente.

## Ubicacion

- Contrato: `app/Services/Anime/Contracts/AnimeProviderInterface.php`
- Provider: `app/Services/Anime/Providers/JkAnimeProvider.php`
- Parser: `app/Services/Anime/Providers/JkAnimeHtmlParser.php`
- DTOs: `app/Services/Anime/Data/*`
- Configuracion: `config/anime.php`
- Inspector: `tools/jkanime-inspector`

## Flujo validado

La fase 2 verifico el comportamiento actual con `one-piece/1175`:

- La pagina de episodio responde `GET /{anime-slug}/{episode}` con `200`.
- La pagina de anime expone el identificador interno usado por
  `POST /ajax/episodes/{anime_id}/{page}`.
- La pagina de episodio contiene iframes `jkplayer`.
- El HTML puede incluir `/ajax/download_episode/{episode_id}`.

El provider usa la ruta publica de busqueda declarada por el formulario actual:

- `GET /buscar?q={query}`, que redirige a `/buscar/{query}`.

`GET /ajax_search` fue verificado como `404` al consultarlo directamente y no se
usa en esta fase.

## Seguridad

- La URL base debe ser `https`.
- El host debe existir en `JKANIME_ALLOWED_HOSTS`.
- El provider construye sus propias rutas; no acepta URLs arbitrarias de usuario.
- No se persisten cookies, tokens CSRF ni parametros efimeros de player.
- `getStream()` solo devuelve HLS/MP4 si existe una URL directa permitida. Los
  iframes `jkplayer` se reportan como servidores `embed` y no se convierten en
  stream.

## Variables

```env
ANIME_ENABLED=true
ANIME_DEFAULT_LANGUAGE=es
ANIME_CACHE_ENABLED=true
ANIME_CACHE_STORE=redis
ANIME_CACHE_MIRROR_DATABASE=true
ANIME_CACHE_SEARCH_TTL=3600
ANIME_CACHE_METADATA_TTL=86400
ANIME_CACHE_EPISODES_TTL=3600
ANIME_CACHE_SERVERS_TTL=300
ANIME_SERVER_PRIORITY=desu,magi
ANIME_REQUEST_TIMEOUT=15
ANIME_CONNECT_TIMEOUT=10
JKANIME_ENABLED=true
JKANIME_BASE_URL=https://jkanime.net
JKANIME_ALLOWED_HOSTS=jkanime.net,www.jkanime.net
JKANIME_USER_AGENT="CodeRED-Platform/4.x Anime Provider"
```

## Validacion

La suite de fase 3 usa `Http::fake()` y no realiza pruebas destructivas ni
dependientes de servicios externos.

```bash
docker compose exec -T app php artisan test tests/Unit/Anime/JkAnimeProviderTest.php
docker compose exec -T app ./vendor/bin/phpstan analyse app/Services/Anime app/Providers/AppServiceProvider.php tests/Unit/Anime/JkAnimeProviderTest.php --memory-limit=1G --configuration=phpstan.neon.dist
```
