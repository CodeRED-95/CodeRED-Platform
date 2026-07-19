# Sincronización del catálogo de agencias

La extensión utiliza sincronización de solo lectura mediante Sanctum y la ability `agencies:read`. La versión de la API (`v1`) es distinta de `schema_version`, que identifica el contrato del catálogo.

## Flujo consistente

1. Solicitar `GET /api/v1/catalog/metadata` y guardar `ETag` y `current_cursor` como cursor de snapshot.
2. Descargar todas las páginas de `GET /api/v1/agencies`. El catálogo completo sigue siendo el mecanismo inicial y de recuperación.
3. Solicitar `GET /api/v1/agencies/changes?cursor=CURSOR_DE_SNAPSHOT`.
4. Crear o reemplazar en la caché cada elemento de `data.upserted` y retirar cada identificador de `data.deleted`.
5. Mientras `meta.has_more` sea verdadero, repetir con `meta.next_cursor`. Guardar únicamente el último cursor aplicado con éxito.
6. En sincronizaciones posteriores, consultar metadata con `If-None-Match`. Un HTTP 304 permite conservar la caché sin descargar el cuerpo.
7. Si la API responde HTTP 409 con `code=full_sync_required`, descartar el cursor y repetir el flujo completo.

Tomar el cursor antes de descargar las páginas evita omisiones: cualquier cambio concurrente queda después del cursor y se aplica en el paso incremental. La extensión debe tratar upserts y deletes como operaciones idempotentes.

## Cursores y retención

El cursor contiene versión, secuencia monotónica y versión de esquema, codificadas en formato URL-safe y firmadas con HMAC. No debe interpretarse ni modificarse. Los cambios se conservan 180 días por defecto. El comando `php artisan agencies:prune-sync-changes --dry-run` muestra el impacto; sin `--dry-run` elimina eventos vencidos y avanza el watermark que invalida cursores antiguos.

## Caché HTTP

Metadata, catálogo paginado y detalle envían ETag, Last-Modified, `Cache-Control: private, must-revalidate` y `Vary: Authorization, Accept-Encoding`. El ETag del catálogo incluye revisión global, filtros, búsqueda, orden, página y tamaño. El endpoint incremental usa `private, no-store`.

## Compresión y Cloudflare

Nginx comprime JSON y formatos de texto con Gzip desde 1024 bytes. Cloudflare puede negociar Brotli o Gzip con la extensión usando `Accept-Encoding`; verificar `Content-Encoding` en producción. Reglas recomendadas:

- bypass de caché cuando exista `Authorization`;
- nunca usar Cache Everything en `/api/v1/*` autenticado;
- no almacenar `/api/v1/me` ni `/agencies/changes`;
- respetar ETag y permitir respuestas 304;
- mantener compresión Brotli/Gzip habilitada;
- no registrar Authorization ni tokens en Cloudflare, Nginx o logs de aplicación.

## Ejemplo

```bash
curl --compressed -i \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -H 'If-None-Match: "ETAG"' \
  https://platform.codered.host/api/v1/catalog/metadata
```
