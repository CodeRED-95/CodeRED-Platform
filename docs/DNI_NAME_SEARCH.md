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

## Módulo del panel

`Identidad → Buscar DNI por nombres` (`/admin/api-tools/dni-name-search`).

Usa el mismo `DniNameSearchService` que el endpoint, así que comparte proveedor,
caché y estados: lo que se ve en el panel es lo que devolvería un token con la
ability `dni:nombre`.

Se autoriza con el permiso RBAC `dni-records.view` —el mismo al que se mapea la
ability—, de modo que retirar el permiso corta a la vez el acceso por panel y
por API. No introduce permisos nuevos.

Cada búsqueda queda en `api_request_logs` con `request_type=admin_test`,
`service=dni-name-search` y el mismo hash de la combinación que usa el endpoint.

## Endpoint

`GET /api/v1/dni/name-search`

Query parameters:
- `nombres`
- `apellido_paterno`
- `apellido_materno`

## Seguridad

La respuesta se marca como referencial y no como validación oficial de RENIEC. El scraper no implementa bypass de CAPTCHA, WAF, autenticación ni bloqueo. Si el sitio deja de exponer un formulario automatizable, el proveedor devuelve un estado controlado.

El audit log guarda un hash de la combinación consultada, no los nombres completos.
