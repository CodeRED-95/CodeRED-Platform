# Architecture Decision Records

## Qué es un ADR

Un ADR es un registro corto y explícito de una decisión arquitectónica importante.

## Cuándo crear uno

Crear un ADR cuando:

- se elige una tecnología base
- se adopta una estrategia de importación o caché
- se define un contrato API estable
- se decide un patrón arquitectónico relevante
- una decisión cambia el comportamiento del sistema

## Cómo numerarlos

- Usar números correlativos de 4 dígitos.
- El número debe ser único y creciente.
- El título debe describir la decisión, no el síntoma.

## Cuándo actualizar uno existente

- Si la misma decisión cambia de alcance.
- Si se corrige una consecuencia importante.
- Si aparece una alternativa nueva que altera la justificación.

## Cuándo crear uno nuevo

- Si la decisión es distinta.
- Si cambiar el ADR anterior lo volvería ambiguo.

## Estructura esperada

Cada ADR debe incluir:

- Título
- Estado
- Contexto
- Problema
- Alternativas consideradas
- Decisión
- Justificación
- Consecuencias
- Referencias

## ADR actuales

| Nº | Título |
|---:|---|
| 0001 | Arquitectura modular del proyecto |
| 0002 | Uso de Laravel como framework base |
| 0003 | Uso de PostgreSQL como base de datos principal |
| 0004 | Uso de Livewire para la interfaz administrativa |
| 0005 | Uso de Docker Compose |
| 0006 | Importación desde GitHub Gist |
| 0007 | Estrategia de caché para la futura extensión Chrome |
| 0008 | Versionado de API en `/api/v1` |
| 0009 | Estrategia de agencias trasladadas |
| 0010 | Campo propio para Centro de Operaciones |
| 0011 | Uso de PhpRedis como cliente Redis |
| 0012 | Usuario `www` y estrategia de permisos para bind mounts |
| 0013 | Reutilización de una sola imagen PHP para app, queue y scheduler |
| 0014 | PHP-FPM master como root y workers como `www` |
| 0015 | Git Safe Directory para `/var/www/html` |
| 0016 | `DB_*` como fuente única para PostgreSQL |
| 0017 | Compilación obligatoria de Vite para generar el manifest |
| 0018 | Credenciales de administrador de desarrollo desde `.env` |
| 0019 | Persistencia de `APP_KEY` |
| 0020 | Índice único parcial para `agencies.source` y `agencies.source_reference` |
