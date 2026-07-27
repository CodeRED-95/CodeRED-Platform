# Arquitectura CodeRED Agent

CodeRED Platform es el centro de control. CodeRED Agent mantiene el estado persistente, firma solicitudes, publica discovery y heartbeat. n8n pasa a ser cliente del agente.

```mermaid
flowchart LR
  P[CodeRED Platform] <--> A[CodeRED Agent]
  N[n8n] --> A
  A --> R[(integration.enc AES-256-GCM)]
  A --> C[Capabilities / Services / Plugins]
```
