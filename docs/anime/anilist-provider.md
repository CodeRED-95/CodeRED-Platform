# AniList Provider

## Proposito

`AniListProvider` es el proveedor principal de metadata para CodeRED Anime. Su responsabilidad es consultar AniList GraphQL, normalizar resultados al modelo unificado de CodeRED Anime y dejar las capacidades de episodios, servidores y streams en otros proveedores como `JkAnimeProvider`.

## Flujo Verificado

Durante la Fase 4 se valido desde `steamdeck-ai` una consulta real contra:

```text
POST https://graphql.anilist.co
```

La respuesta `HTTP 200` confirmo los campos utilizados por el provider:

- `id`
- `title.romaji`
- `title.english`
- `title.native`
- `title.userPreferred`
- `synonyms`
- `description(asHtml: false)`
- `coverImage.large`
- `coverImage.medium`
- `bannerImage`
- `genres`
- `studios`
- `season`
- `seasonYear`
- `status`
- `episodes`
- `relations`
- `characters`

## Responsabilidades

- Buscar anime por titulo.
- Consultar metadata por AniList ID.
- Normalizar datos a `Anime` y `Metadata`.
- Cachear busquedas y metadata si `ANIME_CACHE_ENABLED=true`.
- Aplicar allowlist de dominio antes de ejecutar HTTP.
- Registrar logs estructurados sin credenciales ni payloads sensibles.

## No Responsabilidades

- Resolver episodios.
- Resolver servidores de reproduccion.
- Resolver streams.
- Decodificar players externos.
- Descargar contenido.

## Configuracion

```env
ANILIST_ENABLED=true
ANILIST_BASE_URL=https://graphql.anilist.co
ANILIST_ALLOWED_HOSTS=graphql.anilist.co
ANILIST_USER_AGENT="CodeRED-Platform/4.x Anime Metadata Provider"
ANILIST_SEARCH_LIMIT=10
```

## Matching

`AnimeMatcher` permite relacionar metadata de AniList con candidatos de streaming como JkAnime usando:

- IDs externos compartidos.
- Slug.
- Titulos principales.
- Titulos alternativos.
- Sinonimos.
- Bono pequeno por coincidencia de ano y total de episodios.

El matcher evita depender solo del titulo principal para que se puedan resolver casos como titulo ingles, romaji o alias comun.
