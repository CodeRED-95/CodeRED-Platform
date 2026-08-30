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

El proyecto genera **dos APK** desde el mismo codigo, uno por formato de
dispositivo (product flavors `tv` y `mobile`):

```bash
./gradlew :app:assembleTvDebug :app:assembleMobileDebug
```

| Variante | APK | Diferencias |
|----------|-----|-------------|
| `tv` | `app/build/outputs/apk/tv/debug/app-tv-debug.apk` | Exige leanback, se anuncia solo en el lanzador de Android TV, lleva banner y actua como receptor del envio desde el movil. Interfaz siempre en modo mando. |
| `mobile` | `app/build/outputs/apk/mobile/debug/app-mobile-debug.apk` | Exige pantalla tactil, aparece en el lanzador normal y descubre televisores para enviarles capitulos. Interfaz compacta o de tablet segun el ancho. |

Ambas comparten `applicationId`, asi que no pueden convivir en el mismo
dispositivo; para instalar las dos a la vez (por ejemplo en una tablet de
pruebas) hay que darle a una un `applicationIdSuffix`. Para publicar en Play
como multi-APK, cada variante necesita ademas su propio `versionCode`.

Lo que cambia por variante esta en `src/tv/AndroidManifest.xml`,
`src/mobile/AndroidManifest.xml` y en `BuildConfig.IS_TV_BUILD`.

Si no existe wrapper local, abrir `apps/codered-anime-tv` en Android Studio y
dejar que genere/sincronice Gradle.

## Enviar a la television

La variante `tv` se anuncia en la red local por NSD (`_coderedtv._tcp`) y
expone un servidor minimo con `/ping` y `/play`. La variante `mobile` la
descubre y le manda que anime y capitulo abrir; la television resuelve el
stream con su propio cliente, que es el unico que envia las cabeceras
`Referer` y `Origin` que exige el proveedor.

No es Google Cast: no aparece el icono estandar ni funciona con dongles. El
servidor **no autentica**, asi que cualquiera dentro de la red local puede
lanzar reproducciones en la television.

## Seguridad

- No se guardan cookies ni tokens del usuario.
- No se hace bypass de DRM, autenticacion ni paywalls.
- Las URLs de provider y stream se filtran por allowlist.
- La app solo reproduce URLs directas HLS/MP4 resueltas por el provider.

## Estado

Primera extraccion desde CodeRED Platform. El codigo Laravel existente se deja
intacto para no romper produccion ni migraciones ya aplicadas.
