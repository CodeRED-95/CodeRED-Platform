# JkAnime Inspector

Herramienta de diagnostico para la fase 2 de CodeRED Anime. Su objetivo es
observar el comportamiento actual de una pagina de episodio sin guardar cookies,
tokens, URLs directas de media ni parametros efimeros de player.

## Uso

```bash
npm install
npm run inspect -- one-piece 1175 --collect-static
npm run audit-output
```

Si el host no tiene dependencias de Chromium, ejecutar con la imagen oficial de
Playwright:

```bash
docker run --rm \
  -v "$PWD:/work" \
  -w /work \
  mcr.microsoft.com/playwright:v1.62.1-noble \
  npm run inspect -- one-piece 1175 --collect-static
```

## Modos

- `npm run inspect -- <slug> <episode>`: confirma navegacion Playwright minima.
- `npm run inspect -- <slug> <episode> --collect-static`: suma evidencia del HTML principal.
- `npm run inspect -- <slug> <episode> --static-only`: usa solo HTML estatico, util cuando Chromium no puede iniciar en el host.
- `npm run inspect -- <slug> <episode> --collect-dom`: intenta inspeccion DOM viva. Puede fallar o quedarse lento si la pagina mantiene iframes/scripts externos activos.
- `npm run inspect -- <slug> <episode> --capture-bodies`: captura cuerpos textuales interesantes. Usar solo para depuracion puntual.

## Salida

La carpeta `output/` queda ignorada por Git y contiene:

- `requests.json`
- `responses.json`
- `redirects.json`
- `frames.json`
- `players.json`
- `scripts/external.json`
- `scripts/inline.json`
- `report.json`

Antes de compartir una salida, ejecutar:

```bash
npm run audit-output
```

## Evidencia inicial

Con `one-piece/1175` el 2026-08-28 se verifico:

- `GET https://jkanime.net/one-piece/1175` responde `200`.
- El HTML incluye iframes `jkplayer` con parametros efimeros redactados.
- El HTML incluye `/ajax/download_episode/76722`.
- En una corrida DOM previa se observo `POST /ajax/episodes/201/74`.

Estos datos son evidencia diagnostica, no contrato estable del provider. Antes
de usar un endpoint en codigo productivo se debe volver a ejecutar el inspector
y guardar el reporte operativo correspondiente.

