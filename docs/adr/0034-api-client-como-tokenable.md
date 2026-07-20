# ADR 0034: ApiClient como tokenable

## Decisión

Los nuevos tokens pertenecen preferentemente a `ApiClient`, una identidad activa independiente de usuarios administrativos. Se mantiene temporalmente soporte para tokens de `User` por compatibilidad. Esto evita inferir acceso API desde roles web.
