# Troubleshooting

- 401 local: revise CODERED_AGENT_LOCAL_API_TOKEN.
- unpaired: ejecute /v1/pair.
- requires_repairing: el secreto ya no valida, genere nuevo Pair Code.
- revoked: revise auditoría en Platform; no se borra evidencia automáticamente.

## Error: Agent is unpaired

Causa habitual: CodeRED Platform registró el pairing, pero CodeRED Agent no logró restaurar o leer `/data/integration.enc`. El estado autoritativo del secreto de Platform vive en el agente, no en la credencial n8n.

Verifique:

```bash
docker inspect codered-agent --format 'Estado={{.State.Status}} Reinicios={{.RestartCount}}'
docker exec codered-agent sh -lc 'ls -la /data'
docker exec codered-agent sh -lc 'wget -qO- http://127.0.0.1:5680/healthz'
docker exec codered-agent sh -lc 'wget -qO- http://127.0.0.1:5680/readyz'
docker exec codered-agent sh -lc 'wget -qO- --header="Authorization: Bearer $CODERED_AGENT_LOCAL_API_TOKEN" http://127.0.0.1:5680/api/v1/status'
```

Si `/api/v1/status` devuelve `paired=false`, genere un Pair Code en CodeRED Platform o con `php artisan integrations:n8n-pair-code` y ejecute Pair Agent desde n8n. No copie ni guarde el `shared_secret` en n8n.

## Capabilities en 0 o challenge no publicado

Ejecute Sync Discovery desde n8n o:

```bash
curl -X POST -H "Authorization: Bearer $CODERED_AGENT_LOCAL_API_TOKEN" http://127.0.0.1:5680/v1/discovery/sync
```

La instancia debe publicar al menos `integration.challenge`, `integration.heartbeat`, `integration.discovery`, `integration.status` y `agent.health`.

## Reinicios cada 300 segundos

Ese patrón indica una excepción no controlada durante el ciclo de discovery. El agente ahora envuelve heartbeat/discovery en manejadores seguros y registra `discovery.skipped` cuando está `unpaired`, sin finalizar Node.js.