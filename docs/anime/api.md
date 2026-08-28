# CodeRED Anime API

Estado: fase 6 de CodeRED Anime.

## Autenticacion

Los endpoints de Anime son privados y usan el stack existente de CodeRED:

- `auth:sanctum`
- `api.token-owner-active`
- `api.private-cache`
- `access:anime:read`

Los tokens tecnicos deben incluir la ability:

```text
anime:read
```

Las sesiones de usuario pueden acceder con sesion activa, porque `anime:read`
no exige un permiso RBAC adicional por ahora.

## Endpoints

```http
GET /api/v1/anime/search?q=one+piece
GET /api/v1/anime/{id}
GET /api/v1/anime/{id}/seasons
GET /api/v1/anime/{id}/episodes
GET /api/v1/anime/{id}/episodes/{episode}
GET /api/v1/anime/{id}/episodes/{episode}/servers
GET /api/v1/anime/{id}/episodes/{episode}/stream?server=desu
```

## Respuesta

Todas las respuestas usan un envelope uniforme:

```json
{
  "data": [],
  "meta": {
    "provider": "codered",
    "operation": "search"
  }
}
```

## Providers

Los controllers no conocen URLs de proveedores externos. La orquestacion vive
en `AnimeCatalogService`:

- `AniListProvider`: metadata.
- `JkAnimeProvider`: catalogo reproducible, episodios, servidores y streams.
- `AnimeMatcher`: relaciona metadata AniList con resultados de streaming.

## Rate Limiting

```env
ANIME_RATE_LIMIT_SEARCH=30
ANIME_RATE_LIMIT_METADATA=60
ANIME_RATE_LIMIT_EPISODES=60
ANIME_RATE_LIMIT_STREAM=20
```

Los buckets se calculan por token, usuario o IP usando el mismo helper de
rate limiting de la plataforma.

## Seguridad

- No se aceptan URLs arbitrarias como input.
- `q` se valida para bloquear cadenas sospechosas como URLs internas.
- `server` se limita a identificadores seguros.
- SSRF sigue controlado por allowlists en cada provider.
- La API no expone cookies, tokens ni headers privados.
