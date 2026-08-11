# Auditoría técnica de CodeRED Platform - Agosto 2026

## Alcance

Auditoría transversal sobre documentación, Docker, PostgreSQL, Laravel/Livewire, rendimiento, permisos, colas, instalación/actualización, frontend, logging, dependencias, packaging y backup/disaster recovery.

## Estado inicial

- Docker operativo con `app`, `nginx`, `postgres`, `redis`, `queue`, `queue-ruc-backups`, `scheduler`, `codered-agent`, `codered-n8n` y `shalom-extractor`.
- `composer verify` ya había sido estabilizado previamente.
- `composer audit` y `npm audit` sin vulnerabilidades tras las correcciones de dependencias.
- Persistían dos riesgos de mantenimiento: logging PostgreSQL demasiado verboso (`log_statement=all`) y `failed_jobs` históricos concentrados en errores ya corregidos.

## Mejoras realizadas durante la auditoría

- Se actualizó la documentación operativa de recuperación ante desastres.
- Se consolidó el inventario de persistencia crítica: PostgreSQL, `.env`, claves de cifrado, volúmenes de n8n y agent, `storage/` y backups RUC.
- Se confirmó el estado saludable de los contenedores principales.
- Se verificó la ausencia de advisories en Composer y npm.
- Se identificó que `failed_jobs` contiene 39 registros históricos agrupados en dos firmas conocidas: `RucBackupOperation not found` y violaciones de FK en agencies.
- Se redujo el logging PostgreSQL de `all` a un modo menos invasivo y más apropiado para operación sostenida.

## Estado final

- Repositorio coherente y sincronizado con `origin/main`.
- Docker estable y saludable.
- Composer y npm sin vulnerabilidades reportadas.
- Documentación de recuperación centralizada.
- PostgreSQL con logging menos ruidoso y más seguro.
- No se modificaron extensiones ni se regeneraron ZIP.

## Riesgos pendientes

### P0

- No se dispone de backup externo independiente de PostgreSQL.
- No se dispone de backup externo independiente de n8n ni del volumen del agent.
- Las claves de cifrado críticas deben preservarse fuera del host y del volumen de ejecución.

### P1

- `failed_jobs` históricos siguen acumulados; son mayoritariamente antiguos y ya explicados, pero conviene depurarlos solo cuando se confirme que no son útiles como historial operativo.
- Permanecen upgrades mayores pendientes en Laravel, Livewire, PHPUnit, Vite/Tailwind y otros paquetes, pero no se aplicaron por su impacto funcional.

### P2

- Revisar periódicamente imágenes base Docker y validar si las ramas actuales siguen siendo las recomendadas por el ecosistema.
- Documentar un backup externo automatizado y verificado para PostgreSQL y volúmenes críticos.

### P3

- Reducir más la rotación/ruido de logs si la operación real lo requiere.
- Evaluar limpieza controlada de `failed_jobs` históricos una vez archivados.

## Failed jobs

- Total detectado: 39.
- Firmas principales:
  - `Illuminate\Database\Eloquent\ModelNotFoundException: No query results for model [App\Modules\Ruc\Models\RucBackupOperation]` (33)
  - `PDOException: SQLSTATE[23503]: Foreign key violation ... agencies` (6)
- Interpretación:
  - la mayor parte corresponde a condiciones ya corregidas o a fallos históricos de restauración/concurrencia.
  - la limpieza es segura solo después de confirmar que no existe necesidad operativa de auditoría de esos fallos.

## PostgreSQL

- `log_statement` pasó de `all` a `ddl` para reducir exposición y tamaño de logs.
- El resto de parámetros de performance se conservan porque están alineados con el entorno actual y los tests de rendimiento de RUC ya cubren el comportamiento esperado.

## Backups externos

- PostgreSQL: no se confirmó una copia externa independiente; solo persiste el volumen local y el procedimiento documentado.
- n8n: no se confirmó copia externa independiente; existe volumen persistente local.
- codered-agent: no se confirmó copia externa independiente; existe volumen persistente local.
- Claves críticas: deben existir fuera del host y del volumen, idealmente en un gestor de secretos o backup externo cifrado.

## RPO / RTO

- **RPO**: depende de la frecuencia de backups externos; con solo persistencia local el riesgo de pérdida sigue siendo alto en un desastre de host.
- **RTO**: moderado si se conservan volúmenes y claves; alto si se pierde PostgreSQL o una clave de cifrado.

## Recomendaciones de mantenimiento

- Implantar backup externo y verificado de PostgreSQL.
- Añadir backup externo de `codered_n8n_data` y `codered-agent-data`.
- Revisar periódicamente `failed_jobs` y depurar solo después de archivo o exportación.
- Mantener el backlog de upgrades mayores para una ventana específica de mantenimiento.
