# API CodeRED v1

La API oficial es de solo lectura, usa Laravel Sanctum y se encuentra bajo `/api/v1`. La documentación interactiva interna está en `/docs/api` y requiere Super Administrador.

## Flujo recomendado

1. Crear un token desde **API y Tokens** con `agencies:read`.
2. Copiarlo cuando se muestra por única vez.
3. Enviar `Authorization: Bearer TOKEN` y `Accept: application/json`.
4. Para sincronización eficiente, seguir [Sincronización del catálogo](synchronization.md): metadata con ETag, full sync inicial y `/agencies/changes`.
5. Rotar creando un segundo token; comprobarlo y revocar manualmente el anterior.

No se ha modificado la extensión ni eliminado su fuente Gist.
