# Interfaz CodeRED Anime

La seccion administrativa `CodeRED Anime` vive en `/admin/anime` y reutiliza los componentes del CodeRED Design System.

## Flujo

1. Buscar un titulo por nombre o titulo alternativo.
2. Seleccionar un anime del catalogo unificado.
3. Revisar metadata normalizada y episodios disponibles.
4. Elegir un episodio y servidor.
5. Resolver la fuente de reproduccion mediante `AnimeCatalogService`.

## Limites

- La interfaz no consulta JkAnime, AniList ni otros proveedores directamente.
- No acepta URLs como busqueda ni identificadores arbitrarios.
- No persiste cookies, tokens ni credenciales del proveedor.
- No incluye `hls.js`; Jellyfin o un cliente compatible debe consumir fuentes HLS.
- Si la fuente requiere headers tecnicos, la API mantiene el control de esos datos y el panel evita mostrar secretos.

## Ruta

```text
GET /admin/anime
```

La ruta requiere sesion autenticada y solo aparece en la navegacion cuando `ANIME_ENABLED=true`.
