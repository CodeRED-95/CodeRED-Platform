# Jellyfin Integration

Estado: fase 8 de CodeRED Anime.

## Objetivo

La integracion `CodeRED Anime` permite que Jellyfin descubra anime, metadata,
episodios y fuentes reproducibles usando solo la API de CodeRED Anime.

```text
Jellyfin
  -> CodeRED Anime Plugin
  -> /api/v1/anime
  -> CodeRED Anime Catalog
```

Jellyfin no implementa logica de providers externos. Toda resolucion especifica
permanece dentro de CodeRED Platform.

## Ubicacion

```text
integrations/jellyfin/
```

Archivos principales:

- `CodeRED.Plugin.Anime.sln`
- `CodeRED.Plugin.Anime/Plugin.cs`
- `CodeRED.Plugin.Anime/Configuration/PluginConfiguration.cs`
- `CodeRED.Plugin.Anime/Api/CodeRedAnimeClient.cs`
- `CodeRED.Plugin.Anime/Channels/CodeRedAnimeChannel.cs`
- `scripts/build.sh`
- `scripts/install.sh`

## Configuracion Jellyfin

Crear en CodeRED un token con:

```text
anime:read
```

Luego configurar el plugin:

```text
CodeRedApiBaseUrl=http://codered.local/api/v1/anime
ApiToken=<token anime:read>
PreferredServer=desu
SearchBootstrapQuery=one piece
RequestTimeoutSeconds=15
EnablePlayback=true
```

## Descubrimiento

El canal usa `SearchBootstrapQuery` para poblar la raiz y solicita:

```http
GET /api/v1/anime/search?q={query}&playable=1
```

Ese modo evita mostrar resultados que solo tienen metadata y no tienen episodios
reproducibles. Si una busqueda existe en AniList pero no en el provider de
streaming configurado, Jellyfin no la mostrara como carpeta reproducible.

Cada resultado reproducible se presenta como carpeta de anime. Al abrirlo se
consultan episodios desde:

```http
GET /api/v1/anime/{id}/episodes
```

## Busqueda desde Jellyfin

La pagina de configuracion del plugin incluye `Search and test playback`.
Desde ahi Jellyfin llama a endpoints locales del plugin:

```http
GET /CodeRedAnime/Search?query={query}
GET /CodeRedAnime/Episodes?animeId={id}
GET /CodeRedAnime/Stream?animeId={id}&episode={number}&server={preferred}
```

El controlador del plugin usa internamente `CodeRED Anime API`; Jellyfin no
consulta providers externos directamente. Al pulsar `Use as channel search`, el
titulo se copia a `Default discovery search`; despues de guardar, el canal
`CodeRED Anime` muestra esa busqueda como raiz. El flujo de usuario queda:

```text
Jellyfin plugin settings
  -> Search anime
  -> Load episodes
  -> select episode to test stream
  -> Use as channel search + Save
  -> open CodeRED Anime channel
  -> Anime
  -> Episodes
  -> Play
```

La barra de busqueda global de Jellyfin no envia el texto de busqueda a los
plugins `IChannel` en Jellyfin `10.10.x`. El plugin puede poblar y navegar el
canal `CodeRED Anime`, pero no puede ejecutar busquedas remotas en vivo desde
esa barra sin una integracion mas profunda. Para que la barra global encuentre
anime de CodeRED sin usar `Default discovery search`, se necesita una fase
posterior de sincronizacion/indexacion que materialice los resultados de
CodeRED Anime como items de una biblioteca Jellyfin o un provider remoto
especializado.

## Metadata

El plugin mapea desde CodeRED:

- titulo
- poster
- backdrop/banner cuando el cliente lo soporte
- descripcion
- generos
- ano
- numero de episodio
- titulo de episodio

La fuente principal de metadata sigue siendo AniList dentro de CodeRED.

## Reproduccion

Cuando Jellyfin pide media info de un episodio, el plugin llama:

```http
GET /api/v1/anime/{id}/episodes/{episode}/stream?server={preferred}
```

La respuesta esperada es:

```json
{
  "data": {
    "url": "https://example.test/video.m3u8",
    "type": "hls",
    "format": "m3u8",
    "headers": {},
    "expires_at": null
  }
}
```

El plugin entrega esa URL a Jellyfin como media HTTP directa. Si CodeRED no
puede resolver una fuente compatible, Jellyfin recibe una lista vacia de media
info y debe reportar el error de reproduccion.

## Build

El plugin compila contra Jellyfin `10.10.7` y `net8.0`. En la Steam Deck se
valido con .NET SDK `10.0.111`; no requiere que el servidor Jellyfin conozca
JkAnime ni ningun provider externo. Para compilar:

El comando usa `dotnet publish` por medio del script del repositorio.

```bash
cd integrations/jellyfin
./scripts/build.sh
```

La salida esperada queda en:

```text
integrations/jellyfin/CodeRED.Plugin.Anime/bin/Release/net8.0/publish/
```

## Instalacion

Linux nativo:

```bash
cd integrations/jellyfin
JELLYFIN_PLUGIN_DIR="/var/lib/jellyfin/plugins/CodeRED Anime" ./scripts/install.sh
sudo systemctl restart jellyfin
```

Docker:

```bash
cd integrations/jellyfin
./scripts/build.sh
mkdir -p /ruta/jellyfin/config/plugins/"CodeRED Anime"
cp CodeRED.Plugin.Anime/bin/Release/net8.0/publish/CodeRED.Plugin.Anime.* /ruta/jellyfin/config/plugins/"CodeRED Anime"/
docker restart jellyfin
```

## Seguridad

- El plugin no guarda cookies de navegador.
- El plugin no solicita credenciales de providers externos.
- El token se limita a `anime:read`.
- Las URLs de streams las decide CodeRED.
- Las fuentes directas solo se aceptan si su host esta en
  `JKANIME_STREAM_ALLOWED_HOSTS`.
- No se implementan bypasses de DRM, autenticacion ni paywalls.
