# Consulta DNI por nombres

CodeRED Platform 4.29.0 incorpora una capa de proveedores para consulta de DNI por nombres. El primer proveedor es DNIPERU y se limita al formulario público configurado.

## Activación

En `.env`:

```env
DNI_NAME_SEARCH_ENABLED=true
DNI_NAME_SEARCH_CACHE_ENABLED=true
DNI_NAME_SEARCH_CACHE_TTL=86400
DNI_NAME_SEARCH_RATE_LIMIT_PER_MINUTE=10
DNI_NAME_SEARCH_DNIPERU_ENABLED=true
DNI_NAME_SEARCH_DNIPERU_URL=https://dniperu.com/buscar-dni-por-nombre/
DNI_NAME_SEARCH_DNIPERU_TIMEOUT=15
DNI_NAME_SEARCH_DNIPERU_CONNECT_TIMEOUT=5
DNI_NAME_SEARCH_DNIPERU_RETRIES=1
```

La ability pública es `dni:nombre` y para sesiones de usuario se asocia al permiso RBAC existente `dni-records.view`.

## Endpoint

`GET /api/v1/dni/name-search`

Query parameters:
- `nombres`
- `apellido_paterno`
- `apellido_materno`

## Seguridad

La respuesta se marca como referencial y no como validación oficial de RENIEC. El scraper no implementa bypass de CAPTCHA, WAF, autenticación ni bloqueo. Si el sitio deja de exponer un formulario automatizable, el proveedor devuelve un estado controlado.

El audit log guarda un hash de la combinación consultada, no los nombres completos.
