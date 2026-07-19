# API oficial CodeRED v1

La API de integración vive bajo `/api/v1`, usa tokens personales de Laravel Sanctum y es de solo lectura. El contrato canónico es [OpenAPI 3](openapi.yaml) y la guía operativa está en [docs/api](api/README.md).

## Endpoints

| Método | Ruta | Ability |
|---|---|---|
| GET | `/api/v1/health` | Público |
| GET | `/api/v1/agencies` | `agencies:read` |
| GET | `/api/v1/agencies/{code}` | `agencies:read` |
| GET | `/api/v1/catalog/metadata` | `agencies:read` |
| GET | `/api/v1/me` | `profile:read` |

Los endpoints heredados `search`, `version` y `snapshot` se conservan temporalmente bajo autenticación y `agencies:read` para compatibilidad de transición.

## Contrato de agencia

La respuesta dedicada expone únicamente `internal_id`, `id` externo, `code`, nombre y ubicación en español, enlace de mapa, tamaño y los textos chosen terrestre/aéreo. No serializa el modelo Eloquent completo, eliminadas, auditoría ni procedencia interna.

## Seguridad

- Bearer token; nunca query string.
- 60 solicitudes/minuto/token por defecto.
- Máximo 100 registros/página por defecto.
- Expiración y revocación nativas de Sanctum.
- CORS por lista explícita; sin wildcard ni cookies.
- Errores JSON sin trazas o configuración interna.
- HTTPS obligatorio en producción.

La administración y documentación interactiva son exclusivas de Super Administrador en `/admin/api-tokens` y `/docs/api`.
