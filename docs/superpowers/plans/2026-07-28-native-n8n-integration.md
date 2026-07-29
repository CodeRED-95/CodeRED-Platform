# Native n8n Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make n8n a native CodeRED Platform Compose service with the custom node compiled into the image.

**Architecture:** Root Compose owns every service. docker/n8n/Dockerfile builds packages/n8n-nodes-codered and installs it into n8n custom extensions. Scripts manage only root .env, Postgres role/database, and docker compose.

**Tech Stack:** Docker Compose v2, n8n 2.31.4, Node 24, TypeScript strict, Laravel 12, PHP 8.3, PostgreSQL 16, Redis 7.

## Global Constraints

- No /opt/n8n project creation or rebuild path.
- No edits to dist directly.
- No blind casts such as as any for n8n JSON output.
- Do not delete n8n data or Docker volumes.
- Expose n8n only on 127.0.0.1:5678.
- Pairing continues through codered-agent local API.

---

### Task 1: Compose and Dockerfile

**Files:** docker-compose.yml, docker/n8n/Dockerfile, .dockerignore if needed.

- [ ] Create docker/n8n/Dockerfile multi-stage build.
- [ ] Add codered-n8n service with local build and healthcheck.
- [ ] Add codered_n8n_data volume.
- [ ] Ensure depends_on uses health conditions for postgres, redis, agent.

### Task 2: Environment

**Files:** .env.example, docs/ENVIRONMENT.md.

- [ ] Add N8N_HOST, N8N_EDITOR_BASE_URL, N8N_WEBHOOK_URL, N8N_ENCRYPTION_KEY, N8N_DB_*.
- [ ] Keep CODERED_AGENT_LOCAL_URL and token in root env.
- [ ] Document secret generation.

### Task 3: Scripts

**Files:** Install_CodeRED-Platform.sh, update.sh, CodeRED.sh, tests/Shell/*.

- [ ] Remove /opt/n8n helper usage.
- [ ] Keep idempotent Postgres n8n role/database setup.
- [ ] Use docker compose build/up only.
- [ ] Add tests that fail if /opt/n8n remains in scripts.

### Task 4: TypeScript

**Files:** packages/n8n-nodes-codered/nodes/CodeRED/*.ts, package tests.

- [ ] Ensure sanitizeOutput returns IDataObject without unsafe casts.
- [ ] Ensure LocalAgentClient returns generic parsed payload.
- [ ] Add serialization tests.

### Task 5: Documentation

**Files:** README.md, docs/INSTALL.md, docs/integrations/n8n-docker-installation.md, docs/CHANGELOG.md.

- [ ] Replace /opt/n8n docs with native Compose docs.
- [ ] Add architecture, install, update, backup, restore, Cloudflare DNS/tunnel notes.

### Task 6: Verification

- [ ] bash -n scripts.
- [ ] shell tests.
- [ ] npm build/test where dependencies exist.
- [ ] docker compose config/build where Docker exists.
