# Extension Chrome Buscador Shalom Control

CodeRED Platform es la fuente oficial de agencias para la extension. La extension no realiza scraping, no consulta GitHub Gist y no edita datos.

## Configuracion publica

`GET /api/v1/extension/chrome/config`

Respuesta:

```json
{
  "success": true,
  "data": {
    "platform_name": "CodeRED Platform",
    "api_base_url": "https://platform.codered.host/api/v1",
    "token_request_url": "https://platform.codered.host/solicitar-token",
    "agency_catalog_version": "1",
    "sync_interval_hours": 24,
    "required_scopes": ["agencias:consultar", "agencies:read", "agencies:map"],
    "endpoints": {
      "validate_token": "/api/v1/me",
      "catalog_metadata": "/api/v1/catalog/metadata",
      "agencies": "/api/v1/agencies",
      "changes": "/api/v1/agencies/changes"
    }
  }
}
```

No expone tokens, hashes, secretos ni credenciales internas.

## Token

Enviar siempre `Authorization: Bearer TOKEN`. El token debe tener los scopes del tipo `agencies`: `agencias:consultar`, `agencies:read`, `agencies:map`. Para validar la credencial, la extension usa `/api/v1/me` y luego intenta sincronizar agencias.

## Sincronizacion

La extension consulta metadata y sincroniza el catalogo completo desde `/api/v1/agencies` cuando no tiene cache valida. Cuando conserva cursor, puede usar `/api/v1/agencies/changes`. Una respuesta vacia inesperada no reemplaza la cache local.

## CORS

Configurar `API_ALLOWED_ORIGINS` con los origenes permitidos. Para extensiones empaquetadas, agregar el origen `chrome-extension://<extension-id>` cuando el ID sea estable. En desarrollo, permitir temporalmente el origen de la extension cargada manualmente. Los headers `ETag` y `Last-Modified` estan expuestos para clientes ligeros.

## Cache offline

La extension guarda configuracion, metadata y agencias en `chrome.storage.local`. Las busquedas se ejecutan siempre sobre la copia local, incluso si CodeRED Platform no esta disponible temporalmente.
