# 0016. `DB_*` como fuente única para PostgreSQL

**Estado:** Aceptado

## Contexto

CodeRED Platform usa Laravel y Docker Compose con PostgreSQL como base de datos principal. Durante la instalación aparecieron discrepancias entre las credenciales definidas para Laravel y las que inicializaba el contenedor `postgres`.

## Problema

Mantener variables separadas para Laravel (`DB_*`) y para PostgreSQL (`POSTGRES_*`) aumenta el riesgo de desalineación, especialmente cuando existe un volumen persistente ya inicializado.

## Alternativas consideradas

- Mantener `DB_*` para Laravel y `POSTGRES_*` para Docker.
- Duplicar credenciales en `.env` para ambos sistemas.
- Usar `DB_*` como fuente única y reutilizar esas variables en Docker Compose.

## Decisión

Se adopta `DB_*` como fuente única de credenciales para Laravel y para la inicialización del servicio PostgreSQL en Docker Compose.

## Justificación

- Reduce duplicación.
- Minimiza errores humanos.
- Facilita la documentación y el mantenimiento.
- Hace más claro qué valores deben sincronizarse cuando existe un volumen ya creado.

## Consecuencias

- `docker-compose.yml` depende de `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`.
- Si el volumen de PostgreSQL ya fue creado con otras credenciales, la contraseña interna puede requerir sincronización manual.
- La documentación debe explicar claramente la relación entre el `.env` y el volumen persistente.

## Referencias

- [docs/ENVIRONMENT.md](../ENVIRONMENT.md)
- [docs/INSTALL.md](../INSTALL.md)
