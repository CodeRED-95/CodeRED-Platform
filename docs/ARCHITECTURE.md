# Arquitectura

## Resumen

La aplicación se organiza por dominios en:

- `app/Core`
- `app/Modules`
- `app/Modules/Agencies`

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
