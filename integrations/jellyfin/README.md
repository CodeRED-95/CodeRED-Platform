# CodeRED Anime Jellyfin Plugin

Estado: fase 8 de CodeRED Anime.

Este plugin agrega una integracion de Jellyfin que consume exclusivamente la API
publica de CodeRED Anime:

```text
Jellyfin Plugin -> CodeRED Anime API -> Providers
```

La integracion no conoce ni construye URLs de proveedores de streaming. El
servidor CodeRED decide metadata, episodios, servidores, fallback y streams.

## Configuracion

- `CodeRedApiBaseUrl`: URL base de `/api/v1/anime`.
- `ApiToken`: token con ability `anime:read`.
- `PreferredServer`: preferencia de servidor, por ejemplo `desu`.
- `SearchBootstrapQuery`: busqueda inicial para poblar el canal.
- `RequestTimeoutSeconds`: timeout de HTTP hacia CodeRED.
- `EnablePlayback`: permite resolver streams desde CodeRED.

## Build

Requiere .NET SDK compatible con Jellyfin.

```bash
cd integrations/jellyfin
./scripts/build.sh
```

La version de Jellyfin se puede ajustar sin editar el proyecto:

```bash
dotnet publish CodeRED.Plugin.Anime.sln -p:JellyfinVersion=10.10.7
```

## Instalacion manual

```bash
cd integrations/jellyfin
JELLYFIN_PLUGIN_DIR="/var/lib/jellyfin/plugins/CodeRED Anime" ./scripts/install.sh
sudo systemctl restart jellyfin
```

En Docker, monta o copia el contenido publicado a:

```text
/config/plugins/CodeRED Anime
```

Despues configura el plugin desde el panel de Jellyfin y usa un token CodeRED
con `anime:read`.
