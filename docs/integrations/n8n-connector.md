# n8n CodeRED Connector

Paquete: `@n8n-nodes-codered/codered`. La credencial solo contiene URL de Platform, nombre de instancia, URL publica de n8n y entorno. El Pair Code es un parametro temporal de la operacion Pair Instance.

El nodo se comunica exclusivamente con `codered-agent` mediante `CODERED_AGENT_LOCAL_URL` y Bearer `CODERED_AGENT_LOCAL_API_TOKEN`. No llama directamente al endpoint remoto de pairing de Platform, no accede a workflows internos de n8n y no guarda `shared_secret`, `integration_uuid` ni `instance_uuid`.

Payload de Pair Instance hacia el agente:

```json
{
  "pair_code": "CRD-...",
  "instance_name": "n8n Production",
  "instance_url": "https://n8n.codered.host/",
  "environment": "production",
  "version": "2.31.4",
  "platform_url": "https://platform.codered.host/"
}
```

`codered-agent` agrega su `instance_uuid` persistente desde `/data/agent-identity.json`, reclama el pairing ante Platform, ejecuta challenge, discovery y heartbeat.
