# Instalación Docker n8n CodeRED

No modifique `node_modules` en un contenedor efímero. Cree una imagen derivada y reconstruya n8n cada vez que cambie `packages/n8n-nodes-codered`.

```dockerfile
FROM docker.n8n.io/n8nio/n8n:2.31.4
USER root
COPY ./packages/n8n-nodes-codered /opt/n8n-nodes-codered
RUN cd /opt/n8n-nodes-codered && npm ci && npm run build && npm link
USER node
RUN mkdir -p /home/node/.n8n/custom && cd /home/node/.n8n/custom && npm link @n8n-nodes-codered/codered
```

Variables obligatorias del servicio n8n:

```env
CODERED_AGENT_LOCAL_URL=http://codered-agent:5680
CODERED_AGENT_LOCAL_API_TOKEN=<mismo valor configurado en codered-agent>
N8N_VERSION=2.31.4
```

`codered-n8n` y `codered-agent` deben compartir una red Docker. No use `localhost` desde n8n para hablar con el agente.

Verifique conectividad desde el contenedor n8n sin instalar curl:

```bash
docker exec codered-n8n node -e "fetch('http://codered-agent:5680/healthz').then(async r => { console.log(r.status); console.log(await r.text()); }).catch(error => { console.error(error); process.exit(1); });"
```

Mantenga el volumen persistente de n8n y el volumen `/data` de `codered-agent`. El Pair Code se introduce solo como parámetro temporal de Pair Instance; no se guarda en credenciales.
