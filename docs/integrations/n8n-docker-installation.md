# n8n nativo en CodeRED Platform

n8n forma parte del `docker-compose.yml` principal de CodeRED Platform mediante el servicio `codered-n8n`. No existe un proyecto secundario en `/opt/n8n` y no se descarga `codered-n8n:2.31.4` desde Docker Hub: la imagen se construye localmente desde `docker/n8n/Dockerfile`.

## Estructura

```text
CodeRED-Platform/
├── docker-compose.yml
├── docker/n8n/Dockerfile
├── packages/n8n-nodes-codered/
└── packages/codered-agent/
```

## Servicio Compose

`codered-n8n`:

- build local: `docker/n8n/Dockerfile`
- imagen local: `codered-n8n:2.31.4`
- puerto: `127.0.0.1:5678:5678`
- datos persistentes: `codered_n8n_data:/home/node/.n8n`
- API local del Agent: `http://codered-agent:5680`

## Instalacion y actualizacion

```bash
docker compose up -d --build
```

El Dockerfile compila `packages/n8n-nodes-codered` con `npm ci`, `npm run build`, `npm test` y `npm prune --omit=dev`. Al arrancar, el servicio copia el paquete compilado desde `/opt/n8n-nodes-codered` hacia `/home/node/.n8n/custom/n8n-nodes-codered` dentro del volumen persistente.

## Variables requeridas

```env
N8N_VERSION=2.31.4
N8N_HOST=n8n.codered.lat
N8N_EDITOR_BASE_URL=https://n8n.codered.lat/
N8N_WEBHOOK_URL=https://n8n.codered.lat/
N8N_ENCRYPTION_KEY=
N8N_DB_DATABASE=n8n
N8N_DB_USERNAME=n8n
N8N_DB_PASSWORD=
CODERED_AGENT_LOCAL_URL=http://codered-agent:5680
CODERED_AGENT_LOCAL_API_TOKEN=
```

Genere `N8N_ENCRYPTION_KEY`, `N8N_DB_PASSWORD`, `CODERED_AGENT_ENCRYPTION_KEY` y `CODERED_AGENT_LOCAL_API_TOKEN` con secretos persistentes. No cambie `N8N_ENCRYPTION_KEY` luego de crear credenciales en n8n.

## Cloudflare

Registros esperados:

- `platform.codered.lat` hacia el túnel o proxy de `codered-nginx`.
- `n8n.codered.lat` hacia el túnel o proxy de `codered-n8n` en `127.0.0.1:5678`.
- `agent.codered.lat` hacia el túnel o proxy de `codered-agent` en `127.0.0.1:5680`.

No publique n8n en `0.0.0.0`; Compose liga el puerto solo a loopback para que Cloudflare Tunnel o el proxy local sean el punto de entrada.

## Diagnostico

```bash
docker compose config
docker compose build --no-cache codered-n8n
docker compose up -d --force-recreate codered-n8n
docker compose logs --tail=150 codered-n8n
docker exec codered-n8n node -e "fetch('http://codered-agent:5680/healthz').then(async r => { console.log('HTTP', r.status); console.log(await r.text()); process.exit(r.ok ? 0 : 1); }).catch(e => { console.error(e); process.exit(1); });"
```
