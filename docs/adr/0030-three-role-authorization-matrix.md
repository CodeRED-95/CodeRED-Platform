# 0030. Matriz cerrada de tres roles

**Estado:** Aceptado

## Contexto

Los roles históricos acumulaban solapamientos y podían conceder acceso no previsto mediante traducciones globales de abilities.

## Decisión

El catálogo queda cerrado a `super-admin`, `viewer` y `editor`. Super Administrador conserva bypass total; Consulta usa solo lectura de Agencias y mapa; Editor usa Dashboard y gestión no destructiva de Agencias. Las abilities de modelos se resuelven exclusivamente en sus Policies.

La migración convierte `admin` en `editor` cuando no coexiste con `super-admin`, elimina el rol heredado y sincroniza la matriz. No existe ascenso automático.

## Consecuencias

- Las rutas y acciones deben comprobar autorización en servidor.
- El último Super Administrador activo no puede degradarse, desactivarse ni eliminarse.
- Las redirecciones tras autenticación dependen de capacidades, no del nombre visible del rol.
- Los seeders deben mantener exactamente el mismo catálogo y matriz.

## Referencias

- [Autorización](../AUTHORIZATION.md)
- [Seeders](../SEEDERS.md)
