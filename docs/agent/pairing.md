# Pairing

Genere Pair Code en CodeRED Platform y envíelo al agente:

```bash
curl -H "Authorization: Bearer $CODERED_AGENT_LOCAL_API_TOKEN" -H "Content-Type: application/json" -d {"pair_code":"CRD-XXXXXX"} http://127.0.0.1:5680/v1/pair
```

La respuesta nunca contiene shared_secret.

## Fuente de verdad

La credencial n8n solo guarda `agentBaseUrl`/`agentUrl`, `localApiToken` y timeout. El `instanceSecret` recibido desde CodeRED Platform se cifra y persiste exclusivamente en `/data/integration.enc` dentro del volumen del agente. Después de reiniciar el contenedor, el agente restaura ese estado antes de ejecutar heartbeat o discovery.

La respuesta de Pair Agent solo devuelve identificadores y estado operativo:

```json
{
  "success": true,
  "paired": true,
  "instanceId": "uuid",
  "platformConnected": true,
  "discoveryCompleted": true
}
```