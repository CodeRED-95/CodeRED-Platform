# CodeRED Integration Protocol

## Propósito

Este documento es la fuente única del protocolo compartido por CodeRED Platform, CodeRED Agent y conectores como n8n. Cualquier implementación futura para Flowise, Langflow, Node-RED, Make o Zapier debe implementar esta máquina de estados antes de agregar comportamiento específico del orquestador.

## Máquina de Estados

| Estado | Descripción | Transición principal |
|---|---|---|
| UNPAIRED | No existe pairing persistido. | Pair Instance inicia PAIRING. |
| PAIRING | Se reclama un Pair Code vigente ante Platform. | Respuesta válida guarda credenciales y pasa a CHALLENGING. |
| CHALLENGING | El cliente firma una prueba contra Platform. | Challenge OK pasa a DISCOVERING; fallo cancela el Pair. |
| DISCOVERING | El cliente publica metadata, plugins, servicios y capabilities. | Discovery OK pasa a CONNECTING. |
| CONNECTING | Discovery fue aceptado y falta el primer heartbeat. | Primer heartbeat OK pasa a CONNECTED. |
| CONNECTED | Heartbeat reciente y protocolo confirmado. | Fallos transitorios pasan a DEGRADED. |
| DEGRADED | Hay pairing, pero heartbeat falla temporalmente. | Heartbeat OK vuelve a CONNECTED; demasiados fallos pasan a DISCONNECTED. |
| DISCONNECTED | No hay heartbeat reciente o los reintentos fallaron. | Heartbeat OK vuelve a CONNECTED; 401 pasa a UNAUTHORIZED. |
| UNAUTHORIZED | Platform rechazó la firma o secreto. | Reconnect o rotación de secreto. |
| SECRET_ROTATION_PENDING | Platform generó un secreto pendiente. | SDK lo reclama, persiste y confirma; luego CONNECTED. |

## Pair

El iniciador local ejecuta:

`POST /api/v1/pair`

Payload local:

```json
{
  "pairCode": "CRD-XXXXXX",
  "instanceName": "n8n Production",
  "publicUrl": "https://n8n.codered.lat",
  "environment": "production"
}
```

El agente reclama ante Platform con el contrato público de Platform y persiste cifrado:

- integration_uuid
- shared_secret
- protocol_version
- challenge_url
- heartbeat_url
- discovery_url
- paired_at

La respuesta local al nodo nunca incluye secretos.

## Ciclo Automático

Después de Pair, el ConnectionManager debe ejecutar estrictamente:

1. Persistir credenciales.
2. Challenge firmado.
3. Discovery forzado.
4. Primer heartbeat.
5. Programar heartbeat cada 30 segundos.

Si Challenge falla, el Pair se cancela y se limpia el estado local. Si Discovery o primer Heartbeat fallan, el pairing se conserva y el estado queda degradado para reintentos automáticos.

## Heartbeat

Cada heartbeat envía como mínimo:

- integration_uuid
- timestamp
- uptime
- latency
- workflow_count
- active_executions
- version
- environment

HTTP 401 o 403 detiene la confirmación automática y marca UNAUTHORIZED hasta reconexión o rotación.

## Discovery

Discovery envía como mínimo:

- hostname
- instance_url
- environment
- version
- plugins
- credentials
- services
- capabilities
- workflow_count

Debe publicar al menos:

- integration.challenge
- integration.heartbeat
- integration.discovery
- integration.status

## Confirmación en Platform

Platform solo muestra Conectado cuando recibió:

1. Challenge válido.
2. Discovery válido.
3. Primer heartbeat válido y reciente.

No existe botón Confirmar.
