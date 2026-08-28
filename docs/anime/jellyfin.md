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

El canal usa `SearchBootstrapQuery` para poblar la raiz. Cada resultado se
presenta como carpeta de anime. Al abrirlo se consultan episodios desde:

```http
GET /api/v1/anime/{id}/episodes
```

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

La Steam Deck actual no tiene `dotnet` instalado, asi que en fase 8 se valida
la estructura con tests estaticos del repositorio. Para compilar:

```bash
cd integrations/jellyfin
./scripts/build.sh
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
- No se implementan bypasses de DRM, autenticacion ni paywalls.
