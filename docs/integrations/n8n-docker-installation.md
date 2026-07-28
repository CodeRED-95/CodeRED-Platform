# Instalacion Docker n8n CodeRED

CodeRED usa una imagen local llamada `codered-n8n:2.31.4`. No se descarga desde Docker Hub: el instalador prepara un directorio autocontenido en `/opt/n8n` y ejecuta `docker compose build n8n` antes de recrear el contenedor.

Estructura esperada en el servidor:

```text
/opt/n8n/
├── .env
├── docker-compose.yml
├── Dockerfile
├── data/
└── n8n-nodes-codered/
    ├── package.json
    ├── package-lock.json
    ├── credentials/
    ├── nodes/
    └── dist/
```

El `docker-compose.yml` generado contiene `build.context: .`, `dockerfile: Dockerfile` y `pull_policy: never`. Por eso `/opt/n8n/Dockerfile` y `/opt/n8n/n8n-nodes-codered/package.json` deben existir antes del build.

Variables obligatorias en `/opt/n8n/.env`:

```env
CODERED_AGENT_LOCAL_URL=http://codered-agent:5680
CODERED_AGENT_LOCAL_API_TOKEN=<mismo valor configurado en codered-agent>
N8N_VERSION=2.31.4
```

El instalador sincroniza `CODERED_AGENT_LOCAL_API_TOKEN` desde el `.env` principal y falla si queda vacio o tiene menos de 32 caracteres. No imprime el token completo.

Comandos de verificacion:

```bash
cd /opt/n8n
test -f Dockerfile
test -f docker-compose.yml
test -f n8n-nodes-codered/package.json
docker compose --env-file .env config
docker compose --env-file .env build --no-cache n8n
docker image inspect codered-n8n:2.31.4
docker compose --env-file .env up -d --force-recreate n8n
```

Prueba de red desde n8n hacia el agente, sin instalar curl:

```bash
docker exec codered-n8n node -e "fetch('http://codered-agent:5680/healthz').then(async response => { console.log('HTTP', response.status); console.log(await response.text()); process.exit(response.ok ? 0 : 1); }).catch(error => { console.error(error); process.exit(1); });"
```

Verificacion del paquete dentro de la imagen:

```bash
docker exec codered-n8n sh -lc '
test -d /opt/n8n-nodes-codered/dist && echo "EXTENSION_DIST=OK" || echo "EXTENSION_DIST=MISSING"
grep -R "codered-agent:5680\|CODERED_AGENT_LOCAL_URL" -n /opt/n8n-nodes-codered/dist || true
grep -R "integrations/n8n/pair" -n /opt/n8n-nodes-codered/dist || true
grep -R "instance_uuid.*integrationUuid" -n /opt/n8n-nodes-codered/dist || true
'
```

Resultados esperados: existe `dist`, aparece la URL del agente local, no aparece el endpoint directo de pairing de Platform y no existe el mapeo incorrecto `integrationUuid -> instance_uuid`.

Durante actualizaciones se conserva `/opt/n8n/data`; no use `docker compose down -v`, `docker volume prune` ni `rm -rf /opt/n8n/data`. Si Docker intenta descargar `codered-n8n:2.31.4`, el problema es de configuracion local: falta `build`, falta `Dockerfile` o falta el contexto `n8n-nodes-codered`.
