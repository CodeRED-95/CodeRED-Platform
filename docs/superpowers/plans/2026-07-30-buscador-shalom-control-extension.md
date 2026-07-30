# Buscador Shalom Control Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Chrome Manifest V3 extension that consumes CodeRED Platform as the official agencies source, caches agencies locally, and supports offline search after first sync.

**Architecture:** Add a standalone TypeScript package under `packages/codered-chrome-extension`. Reuse existing `/api/v1` agency, metadata, incremental sync, and token profile endpoints, adding only a public Chrome extension configuration endpoint and safe resource fields when required.

**Tech Stack:** Chrome Manifest V3, TypeScript, Vite, Vitest, ESLint, Prettier, Laravel/PHPUnit for backend contract changes.

## Global Constraints

- CodeRED Platform is the only official agency source.
- Do not scrape Shalom, GitHub Gist, or static JSON as primary data source.
- Use `Authorization: Bearer TOKEN`; never log or embed tokens.
- Searches run only against `chrome.storage.local` cache.
- Ignore `zone` completely; never use it as `district`.
- Public results include only active, moved, and temporarily closed agencies.
- Preserve valid local cache on network, auth, empty response, or sync errors.
- Reuse existing scopes: `agencias:consultar`, `agencies:read`, `agencies:map`.

---

### Task 1: Backend Extension Configuration And Public Agency Fields

**Files:**
- Create: `app/Http/Controllers/Api/V1/ExtensionChromeConfigController.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Resources/Api/V1/AgencyResource.php`
- Modify: `config/api.php`
- Modify: `config/cors.php`
- Test: `tests/Feature/ChromeExtensionConfigTest.php`
- Test: `tests/Feature/AgencyChromeContractTest.php`

**Interfaces:**
- Produces: `GET /api/v1/extension/chrome/config`
- Produces agency fields consumed by extension adapter.

- [ ] Write failing feature tests for public config and agency payload fields.
- [ ] Run targeted PHPUnit tests and verify they fail for missing route/fields.
- [ ] Implement controller, route, config defaults, CORS exposed headers, and safe resource fields.
- [ ] Run targeted PHPUnit tests and verify they pass.

### Task 2: Extension Core Models, Adapter, Search, Maps, Storage, Sync

**Files:**
- Create package files under `packages/codered-chrome-extension/`
- Test: `packages/codered-chrome-extension/tests/*.test.ts`

**Interfaces:**
- Produces: `adaptAgency(input): Agency`
- Produces: `searchAgencies(agencies, query): AgencySearchResult[]`
- Produces: `buildMapsUrl(agency): string`
- Produces: `maskToken(token): string`
- Produces: `syncAgencies(client, storage): SyncResult`

- [ ] Write failing Vitest coverage for normalization, adapter compatibility, public filtering, maps priority, token masking, cache preservation, empty sync, 401/403, and runtime message contracts.
- [ ] Run package tests and verify they fail before implementation.
- [ ] Implement minimal TypeScript modules to pass.
- [ ] Run package tests and verify they pass.

### Task 3: Extension UI And Manifest

**Files:**
- Create: popup, options, service worker, styles, manifest, icons, Vite build config.

**Interfaces:**
- Popup sends typed runtime messages to service worker.
- Options saves config, validates token, opens token request URL, syncs now, deletes token while keeping cache.

- [ ] Implement MV3 manifest with minimal permissions: `storage`, `alarms`, `tabs`.
- [ ] Implement popup and options UI using safe DOM text assignment.
- [ ] Implement service worker alarm and message handlers.
- [ ] Run lint, typecheck, tests, and build.

### Task 4: Documentation And Packaging

**Files:**
- Create: `packages/codered-chrome-extension/README.md`
- Create: `packages/codered-chrome-extension/CHANGELOG.md`
- Create: `docs/api/chrome-extension.md`
- Modify: `docs/api/README.md`
- Modify: `docs/CHANGELOG.md`

- [ ] Document endpoints, scopes, CORS, token request, local cache, development install, build, and ZIP packaging.
- [ ] Add `npm run package` to produce a ZIP from `dist/`.
- [ ] Run final verification commands and report exact outcomes.
