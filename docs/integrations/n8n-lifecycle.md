# Ciclo de Vida n8n CodeRED

## Arquitectura

El nodo n8n no es un daemon. Solo invoca la API local de CodeRED Agent y termina con el workflow. CodeRED Agent mantiene identidad, secreto, challenge, discovery, heartbeat, reconexión y rotación.

## Flujo

1. El usuario ejecuta `Pair Instance`.
2. El nodo llama `POST {CODERED_AGENT_LOCAL_URL}/api/v1/pair` con Bearer `CODERED_AGENT_LOCAL_API_TOKEN`.
3. El agente reclama el Pair Code ante Platform usando `instance_uuid` estable.
4. El agente cifra `/data/identity.json` con `CODERED_AGENT_ENCRYPTION_KEY`.
5. El agente ejecuta Challenge firmado HMAC.
6. El agente registra Discovery sin usar APIs privadas de n8n.
7. El agente envía el primer Heartbeat.
8. Platform muestra `connected` solo con challenge, discovery y heartbeat válidos.

## Máquina de Estados

`UNPAIRED -> PAIRING -> CHALLENGING -> DISCOVERING -> CONNECTING -> CONNECTED`

Fallos temporales llevan a `DEGRADED` y luego `DISCONNECTED`. Un 401/403 requiere reconexión o rotación.

## Persistencia

El agente guarda identidad cifrada en `/data/identity.json` con permisos `0600`. El volumen Docker debe montar `codered-agent-data:/data`.

## Docker

n8n debe usar `http://codered-agent:5680`, no `localhost`, cuando comparte red Docker con el agente.

## Operación

Estado:

```bash
docker exec codered-agent sh -lc 'wget -qO- --header="Authorization: Bearer $CODERED_AGENT_LOCAL_API_TOKEN" http://127.0.0.1:5680/api/v1/status'
```

Logs:

```bash
docker logs --tail 200 codered-agent
```

Duplicados:

```bash
php artisan codered:n8n:deduplicate --dry-run
php artisan codered:n8n:deduplicate
```

Rotar secreto expuesto:

1. Generar Pair Code o rotación desde Platform.
2. Ejecutar `Pair Instance` o `POST /api/v1/rotate-secret` desde el agente.
3. Confirmar que `/api/v1/status` muestra `state=connected`.
4. Verificar que ningún output contiene `shared_secret`.
