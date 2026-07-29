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

## Token Requests

The node exposes a **Token Requests** resource with these operations:

- **Create Token Request**: sends requester details, application name, purpose, requested scopes, expiration and metadata to CodeRED Agent.
- **Get Token Request Status**: reads safe request status and timestamps.
- **Retrieve Approved Token**: retrieves the approved token once. The token is returned only by this operation and is not logged by the node or agent.
- **Confirm Token Delivery**: marks a retrieved token as delivered through `manual`, `telegram`, `whatsapp` or `email`.
- **Cancel Token Request**: cancels a pending request.

All operations call `codered-agent` first. n8n never signs Platform requests and never receives `shared_secret`, integration secrets or agent encryption keys.

Example workflow:

1. Manual Trigger.
2. CodeRED → Token Requests → Create Token Request.
3. Approve the request in CodeRED Platform.
4. CodeRED → Get Token Request Status.
5. CodeRED → Retrieve Approved Token.
6. Deliver the token through the chosen channel.
7. CodeRED → Confirm Token Delivery.

To refresh the functional capabilities shown in Platform, run CodeRED → Connection → Refresh Discovery after deploying a new node/agent build.
