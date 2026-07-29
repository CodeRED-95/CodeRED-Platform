# Native n8n Integration Design

## Goal

CodeRED Platform owns n8n as a first-class Docker Compose service. A clean install or update must not create or depend on /opt/n8n; the canonical operation is docker compose up -d --build from the repository root or the installer wrapper.

## Architecture

The root docker-compose.yml defines codered-n8n next to app, nginx, postgres, redis, queue, scheduler, shalom-extractor and codered-agent. The n8n image is built locally from docker/n8n/Dockerfile using packages/n8n-nodes-codered as build input. Runtime data lives in the named volume codered_n8n_data.

## Data flow

n8n loads the CodeRED custom node from /home/node/.n8n/custom/n8n-nodes-codered. Pair Instance calls codered-agent over http://codered-agent:5680 with CODERED_AGENT_LOCAL_API_TOKEN. codered-agent remains responsible for instance_uuid, shared_secret, challenge, discovery and heartbeat.

## Configuration

All required values are in the root .env/.env.example. n8n uses N8N_* database, host, URL and encryption variables. codered-agent and codered-n8n share CODERED_AGENT_LOCAL_API_TOKEN through the same Compose project.

## Migration

Legacy scripts and documentation that prepare /opt/n8n are removed or rewritten. Existing /opt/n8n data is not deleted by scripts; operators can archive it manually after migrating workflows/credentials into the named Compose volume.

## Testing

Shell tests validate script behavior no longer references /opt/n8n and compose contains codered-n8n. TypeScript tests validate LocalAgentClient serialization and n8n output typing. Docker validation is docker compose config and docker compose build --no-cache codered-n8n where Docker is available.
