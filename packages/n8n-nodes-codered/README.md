# @n8n-nodes-codered/codered

Official self-hosted n8n connector for CodeRED Platform. Secrets are stored only in n8n encrypted credentials.

## Build

`npm ci && npm run build`

## Credential

Create a CodeREDApi credential with the local CodeRED Agent URL, local API token, timeout, instance name, public URL and environment. Run Pair Instance with a temporary Pair Code from CodeRED Platform. The node calls its ConnectionManager, which delegates to codered-agent for Pair, Challenge, Discovery and the first Heartbeat. Manual Discovery, Heartbeat and Challenge operations are intentionally not exposed.
