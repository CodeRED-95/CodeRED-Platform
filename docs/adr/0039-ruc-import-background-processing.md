# ADR 0039: integrar RUC y procesar el padrón en segundo plano

- Estado: aceptado
- Fecha: 2026-07-21

## Contexto

El padrón reducido SUNAT puede tener varios millones de líneas. La API FastAPI de referencia mantenía su propia base, autenticación y despliegue, lo que duplicaba operación y credenciales.

## Decisión

RUC se integra como módulo de dominio de Laravel. PostgreSQL conserva el padrón, Redis acelera consultas exactas y Laravel Queue procesa TXT desde almacenamiento privado. El proceso usa streaming y lotes, registra progreso persistente y errores por línea. Las importaciones ordinarias solo agregan RUC inexistentes; una actualización destructiva requerirá un flujo futuro explícito.

Las abilities `ruc:consultar` y `ruc:buscar` se mantienen separadas de DNI y agencias. El panel y probador usan permisos web `ruc.*`.

## Consecuencias

- CodeRED opera un solo sistema de autenticación, auditoría y despliegue.
- El worker y Redis son requisitos operativos para padrones grandes.
- Un archivo puede reintentarse de forma idempotente, pero cancelar no revierte filas ya confirmadas.
- La búsqueda parcial requiere `pg_trgm`; la extensión ya forma parte del esquema del proyecto.

