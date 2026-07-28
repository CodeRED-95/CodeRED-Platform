# @n8n-nodes-codered/codered

Official self-hosted n8n connector for CodeRED Platform.

The node never stores `shared_secret`, `integration_uuid` or `instance_uuid` in n8n credentials. Pair Instance talks only to the local CodeRED Agent; the agent owns identity, pairing, challenge, discovery, heartbeat and secret rotation.

## Build

```bash
npm ci
npm run build
npm test
```

## Required n8n environment

```env
CODERED_AGENT_LOCAL_URL=http://codered-agent:5680
CODERED_AGENT_LOCAL_API_TOKEN=<same token configured in codered-agent>
N8N_VERSION=2.31.4
```

## Credential

Create a CodeREDApi credential with only:

- CodeRED Platform URL
- Instance Name
- Public n8n URL
- Environment

Pair Code is a temporary Pair Instance parameter. It is not stored in the credential and is never returned in node output.

## Pair Instance flow

Pair Instance posts to `{CODERED_AGENT_LOCAL_URL}/api/v1/pair` with a Bearer token from `CODERED_AGENT_LOCAL_API_TOKEN`. The payload contains `pair_code`, instance metadata, n8n version and `platform_url`. It never includes `instance_uuid`, `integration_uuid` or secrets; codered-agent adds its persistent `instance_uuid` from `/data/agent-identity.json` before contacting Platform.
