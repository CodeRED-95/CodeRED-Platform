# Seguridad

## Autenticación

- Web: guard `web`
- API: Sanctum preparado para futuras credenciales/token

## Autorización

- Policies para `Agencies`
- Permisos granulares por módulo

## CSRF

La web usa protección CSRF estándar de Laravel.

## Rate limiting

La API usa limitación por IP/usuario en `AppServiceProvider`.

## Validaciones

- Form Requests
- reglas por campo
- enum validation donde aplica

## Protección SSRF

El importador solo permite:

- `https`
- `gist.githubusercontent.com`
- `raw.githubusercontent.com`

## Protección XSS

- Escape por Blade
- No renderizar contenido no confiable sin escape

## Protección SQL Injection

- Query Builder / Eloquent
- `whereRaw` restringido a patrones conocidos

## Buenas prácticas

- No guardar secretos en el repositorio
- No exponer trazas en producción

