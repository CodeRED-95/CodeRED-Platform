# 0031. API de solo lectura con Sanctum

**Estado:** Aceptado

## Contexto

La extensión necesita migrar progresivamente desde Gist a un catálogo oficial sin exponer escritura ni credenciales de usuario.

## Decisión

La API v1 usa tokens personales Sanctum, abilities mínimas, expiración, revocación, límites por token y CORS explícito. El contrato se publica mediante Resource dedicado y OpenAPI 3. La administración pertenece únicamente a Super Administrador.

La documentación interactiva renderiza el contrato canónico con Swagger UI dentro del Design System. Permite Authorize y Try it out, pero configura persistAuthorization en false para que el bearer permanezca solo en memoria. Los endpoints heredados permanecen temporalmente autenticados durante la transición.

## Consecuencias

- La extensión debe declarar `host_permissions`, almacenar el token de forma segura y solicitar `agencies:read`.
- Gist no se elimina hasta completar la migración del cliente.
- No existen endpoints de escritura en v1.
- Rotar no revoca automáticamente el token anterior.

## Referencias

- [API](../API.md)
- [Autenticación](../api/authentication.md)
- [OpenAPI](../openapi.yaml)
