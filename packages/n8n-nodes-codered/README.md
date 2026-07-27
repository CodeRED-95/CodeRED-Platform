# @n8n-nodes-codered/codered

Official self-hosted n8n connector for CodeRED Platform. Secrets are stored only in n8n encrypted credentials.

## Build

`npm ci && npm run build`

## Credential

Create a CodeREDApi credential, enter CodeRED URL, instance metadata and a temporary Pair Code from CodeRED. Run the CodeRED node operation Pair Instance once, then store returned integration UUID and shared secret in the credential fields.
