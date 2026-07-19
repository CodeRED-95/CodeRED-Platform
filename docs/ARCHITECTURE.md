# Arquitectura

## Resumen

La aplicación se organiza por dominios en:

- `app/Core`
- `app/Modules`
- `app/Modules/Agencies`
- `resources/views/components/ui`

## Design System

```mermaid
flowchart LR
    Design[CodeRED Design System] --> Tokens[Tokens semánticos]
    Design --> Components[Blade Components]
    Tokens --> Layout[Layout admin]
    Tokens --> Login[Login]
    Tokens --> Dashboard[Dashboard]
    Components --> Agencies[Módulo Agencies]
    Components --> Public[Vista pública]
```

El diseño visual se centraliza en tokens CSS y componentes Blade compartidos para evitar estilos dispersos.

## Diagrama general

```mermaid
flowchart LR
    Browser --> Nginx
    Nginx --> App[Laravel / PHP-FPM]
    App --> Postgres[(PostgreSQL)]
    App --> Redis[(Redis)]
    App --> Queue[Queue Worker]
    App --> Scheduler[Scheduler]
```

## Docker

```mermaid
flowchart TB
    subgraph Compose[Docker Compose]
        app[app]
        nginx[nginx]
        postgres[postgres]
        redis[redis]
        queue[queue]
        scheduler[scheduler]
    end
```

## Flujo API

### Sincronización incremental de agencias

El catálogo público autenticado se sincroniza mediante un changelog append-only independiente de la auditoría administrativa. `agency_sync_changes.id` es la secuencia monotónica; los cursores opacos están firmados con HMAC y contienen esa secuencia y la versión del esquema. El observer de `Agency` escribe eventos `upsert` o `delete` dentro de la misma transacción del cambio. `agency_sync_states` conserva el watermark de retención para detectar cursores vencidos y exigir una sincronización completa. Véase [ADR 0032](adr/0032-agency-incremental-sync-changelog.md).


```mermaid
sequenceDiagram
    participant C as Cliente
    participant A as Laravel
    participant P as PostgreSQL
    participant R as Redis

    C->>A: GET /api/v1/agencies
    A->>R: Consulta caché/versión
    A->>P: Consulta agencias
    P-->>A: Resultado
    A-->>C: JSON
```

## Importador

```mermaid
flowchart TD
    U[URL raw Gist] --> V[Validación SSRF]
    V --> J[Descarga JSON]
    J --> P[Vista previa]
    P --> Q[Procesamiento en cola]
    Q --> D[Persistencia]
```

## Autenticación

```mermaid
flowchart LR
    U[Usuario] --> L[Login Livewire]
    L --> S[Session / Web Guard]
    S --> A[Panel Administrativo]
    U --> T[Sanctum]
    T --> API[API /api/v1]
```

## Base de datos

```mermaid
erDiagram
    users ||--o{ role_user : has
    roles ||--o{ role_user : assigned
    roles ||--o{ permission_role : grants
    permissions ||--o{ permission_role : included
    users ||--o{ agencies : creates
    users ||--o{ agencies : updates
    agencies ||--o{ agency_change_logs : logs
    agencies ||--o{ agency_imports : imports
    agency_imports ||--o{ agency_import_failures : failures
```

## Factories y seeders

```mermaid
flowchart LR
    DatabaseSeeder --> RolesSeeder
    DatabaseSeeder --> PermissionsSeeder
    DatabaseSeeder --> SettingsSeeder
    DatabaseSeeder --> AdminSeeder
    DatabaseSeeder --> AgencySeeder
    AgencySeeder --> AgencyFactory
```

Los modelos modulares que usan factories centralizadas en `database/factories` deben implementar `newFactory()` para no depender de la inferencia automática.

## Flujo de solicitudes

```mermaid
flowchart LR
    Request --> Middleware --> Controller --> Service --> Model --> DB --> Response
```

## Extensión Chrome

La extensión Chrome todavía no está implementada.

PENDIENTE DE CONFIGURAR
