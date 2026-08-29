# CodeRED Anime TV

Aplicacion Android TV independiente para buscar anime, listar episodios y
reproducir fuentes HLS/MP4 resueltas desde JkAnime.

Esta app no depende de Jellyfin ni de la API Laravel de CodeRED Platform. La
logica especifica de JkAnime vive en `data/JkAnimeClient.kt` y
`data/JkAnimeParser.kt`.

## Arquitectura

```text
Android TV UI
  -> AnimeTvViewModel
  -> JkAnimeClient
  -> JkAnimeParser
  -> JkAnime
  -> Media3 Player
```

## Funciones iniciales

- Busqueda por titulo.
- Detalle basico desde la pagina del anime.
- Carga de episodios usando el indice actual y `/ajax/episodes/{id}/{page}`.
- Resolucion de servidores desde la pagina de episodio.
- Resolucion de HLS/MP4 desde embeds permitidos.
- Reproduccion con AndroidX Media3.

## Requisitos

- Android Studio actual.
- Android SDK 35.
- JDK compatible con Android Gradle Plugin.

## Build

Desde esta carpeta:

```bash
./gradlew :app:assembleDebug
```

Si no existe wrapper local, abrir `apps/codered-anime-tv` en Android Studio y
dejar que genere/sincronice Gradle.

## Seguridad

- No se guardan cookies ni tokens del usuario.
- No se hace bypass de DRM, autenticacion ni paywalls.
- Las URLs de provider y stream se filtran por allowlist.
- La app solo reproduce URLs directas HLS/MP4 resueltas por el provider.

## Estado

Primera extraccion desde CodeRED Platform. El codigo Laravel existente se deja
intacto para no romper produccion ni migraciones ya aplicadas.
