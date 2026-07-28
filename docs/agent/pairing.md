# Pairing

Genere Pair Code en CodeRED Platform y envíelo al agente:

```bash
curl -H "Authorization: Bearer $CODERED_AGENT_LOCAL_API_TOKEN" -H "Content-Type: application/json" -d '{"pairCode":"CRD-XXXXXX","instanceName":"n8n Production","publicUrl":"https://n8n.codered.host","environment":"production"}' http://127.0.0.1:5680/api/v1/pair
```

La respuesta nunca contiene shared_secret.

## Fuente de verdad

La credencial n8n solo guarda `agentBaseUrl`, `localApiToken` y `timeoutMs`. El `shared_secret` recibido desde CodeRED Platform se cifra y persiste exclusivamente en `/data/integration.enc` dentro del volumen del agente. Después de reiniciar el contenedor, el agente restaura ese estado antes de ejecutar heartbeat o discovery.

La respuesta de Pair Agent solo devuelve identificadores y estado operativo:

```json
{
  "success": true,
  "paired": true,
  "instanceId": "uuid",
  "protocolVersion": "1.0",
  "pairedAt": "2026-07-28T00:00:00.000Z",
  "platformConnected": true,
  "heartbeatCompleted": true,
  "discoveryCompleted": true
}
```