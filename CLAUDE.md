# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## Project Overview

**CodeRED Platform** is a modular Laravel 12 + Livewire 3 application for managing Peruvian business/agency data (DNI/RUC queries), API tokens, and integrations with n8n via CodeRED Agent.

**Current version:** 2.2.0 (semantic versioning with automatic bumping via git hooks)

**Key features:**
- Multi-tenant agency management (Shalom integration)
- Secure token request flow with OTP validation and AES-256-CBC encryption
- RUC Import System v3.0: streaming file processing for unlimited file sizes, event sourcing, rollback support
- n8n integration via persistent CodeRED Agent daemon
- Real-time progress tracking via Livewire broadcasting
- Complete audit trail for all operations

---

## Stack & Dependencies

### Backend (PHP)
- **Laravel 12** — framework
- **Livewire 3** — reactive components (replaces Vue/React for UIs)
- **Sanctum 4** — API token authentication (ability-based scopes)
- **PostgreSQL 16** — primary database, row-level locking for race condition prevention
- **Redis 7** — queue, cache, session storage
- **Spatie Data** — typed data classes for type safety

### DevOps & Tools
- **Docker Compose** — local dev, app/queue/scheduler/nginx/postgres/redis/n8n/agent
- **PHP 8.3** — strict types, enums, match expressions
- **Pint** — code formatting (Laravel standard)
- **PHPStan Level 9** — static analysis
- **PHPUnit 11** — testing

### Frontend
- **Livewire 3** — reactive components with Alpine.js
- **Blade templates** — server-side rendering
- **Tailwind CSS** — styling (via npm)
- **Chrome Extension** — separate versioning in `packages/codered-chrome-extension`

### External Services
- **n8n 2.31.4** — automation/workflow engine (native Docker service)
- **CodeRED Agent** — persistent daemon for Pairing, Discovery, Heartbeat, capability registry

---

## Directory Structure

```
app/
├── Modules/                    # Module-per-feature architecture
│   ├── Ruc/                    # RUC Import System (Agencies, DNI, RUC queries)
│   ├── Agencies/               # Shalom agency management
│   └── Users/                  # User management, roles, permissions
├── Actions/                    # Business logic (request → DB changes)
├── Http/
│   ├── Controllers/            # API/web endpoints
│   ├── Requests/               # Form validation
│   └── Middleware/             # Middleware (auth, roles, etc.)
├── Livewire/                   # Reactive UI components
├── Models/                     # Eloquent models
├── Services/                   # Shared services (encryption, audit, OTP, etc.)
├── Enums/                      # PHP enums (statuses, roles, audit actions)
├── Events/                     # Domain events (for broadcasting, listeners)
├── Listeners/                  # Event subscribers
├── Mail/                       # Mailable classes (OTP, notifications)
├── Policies/                   # Authorization (Gate integration)
├── Exceptions/                 # Custom exceptions (domain-specific)
└── Providers/                  # Service provider registration

database/
├── migrations/                 # Schema changes (idempotent, check-before-add)
├── seeders/                    # Data seeders (rerunnable for roles/permissions)
└── factories/                  # Model factories for testing

config/                         # Environment-based config
├── version.php                 # Version (also in composer.json extra)
├── queue.php                   # Queue connections (ruc-imports + default)
├── token-requests.php          # OTP, encryption, audit settings
└── ...

resources/
├── views/                      # Blade templates (public, admin, emails)
└── css/                        # Tailwind/SCSS

routes/
├── api.php                     # API endpoints (/api/v1/*)
├── web.php                     # Web routes (admin panel, public forms)
└── channels.php                # Broadcasting channels (Livewire progress)

tests/
├── Feature/                    # Integration tests (hit DB, real services)
└── Unit/                       # Unit tests (isolated)

docker/
├── php/Dockerfile              # PHP 8.3 + extensions
├── nginx/default.conf          # Nginx config (reverse proxy to php-fpm)
└── postgres/initdb/            # Init scripts (roles, DBs)

bin/
├── setup-git-hooks.sh          # Enable git hook for version bumping
└── git-hooks/prepare-commit-msg # Auto-detect feat|fix|BREAKING for versioning

docs/                           # General docs (INSTALL, ARCHITECTURE, SECURITY, etc.)
docs-ruc/                       # RUC v3.0 specific (IMPLEMENTATION, QUICK_START, etc.)
docs-dev/VERSIONING.md          # Semantic versioning system
docs/DOCUMENTATION_STRUCTURE.md # Map of all docs

.env.example                    # Template for environment variables
CHANGELOG.md                    # Master changelog (auto-updated by version bumps)
README.md                       # Quick start, main features
```

---

## Key Architecture Patterns

### 1. Module-Per-Feature (app/Modules/)
Each feature (Ruc, Agencies, Users) is self-contained:
```
Modules/Ruc/
├── Models/RucImport.php
├── Http/Controllers/RucImportControllerV3.php
├── Http/Requests/CreateImportRequest.php
├── Services/RucFileStreamReader.php  # Streaming (O(1) memory)
├── Services/RucBatchInserter.php     # UPSERT without staging table
├── Jobs/ProcessRucImportJobV3.php    # Queue job
├── Policies/RucImportPolicy.php      # Authorization
├── Events/RucImportProgressUpdated.php
└── Enums/RucImportStatusV3.php
```

**Why:** Cohesion by feature, not by layer. Easier to find related code, test, and deploy independently.

### 2. Actions Pattern (app/Actions/)
Business logic is in Action classes, not controllers:
```php
// app/Actions/ApiTokenRequests/RevealTokenAction.php
class RevealTokenAction {
    public function execute(ApiTokenRequest $request, ...): string {
        return DB::transaction(function() { ... });  // Atomic
    }
}

// In controller: $token = (new RevealTokenAction())->execute(...);
```

**Why:** Reusable, testable, encapsulates domain rules. Separates request handling from business logic.

### 3. Event Sourcing (RUC v3.0)
Every import action (create, process, error, rollback) is logged to `ruc_import_events`:
```sql
INSERT INTO ruc_import_events (ruc_import_id, event_type, data, created_by, ip_address, ...)
VALUES (123, 'import.started', {...}, user_id, '192.168.1.1', ...)
```

**Why:** Complete audit trail, ability to replay, debugging, compliance.

### 4. Streaming File Processing (RUC v3.0)
Uses `SplFileObject` to read files line-by-line:
```php
$file = new SplFileObject($path, 'r');
foreach ($file as $line) {
    $result = $validator->validate($line);  // Process 1 line at a time
    $file->eof() || $file->next();
}
```

**Result:** O(1) memory regardless of file size (tested up to 10GB+). No staging table.

### 5. Broadcasting for Real-Time Progress
Livewire components subscribe to private channels:
```php
// In Job: broadcast(new RucImportProgressUpdated($import, $progress));
// In Component: #[On('echo:private-ruc-import-123,RucImportProgressUpdated')]
```

**Why:** Sub-second UI updates without polling. Uses Laravel's broadcasting (configured for log in dev, can be upgraded to Pusher/Ably).

### 6. PostgreSQL-Specific: Row-Level Locking
Prevents race conditions during token revelation:
```php
$request = ApiTokenRequest::query()
    ->where('id', $id)
    ->lockForUpdate()  // SELECT ... FOR UPDATE
    ->first();

DB::transaction(fn() => $request->update(['revealed' => true]));
```

**Why:** Atomicity. No two requests can reveal same token simultaneously.

### 7. Encryption & Blind Indexing
Personal data is encrypted AES-256-CBC; email is blind-indexed for search:
```php
// On save:
$data['requester_email'] = Crypt::encryptString($email);
$data['email_hmac'] = hash_hmac('sha256', $email, env('BLIND_INDEX_KEY'));

// On search:
ApiTokenRequest::where('email_hmac', hash_hmac(...))->first();
```

**Why:** Compliance. Email never stored plaintext; searches still work.

---

## Common Commands

### Development Setup
```bash
# First time only
cp .env.example .env
docker compose up -d
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan db:seed
./bin/setup-git-hooks.sh enable  # Enable automatic version bumping

# Daily
docker compose up -d
```

### Testing
```bash
# All tests
composer test

# By suite
composer test-unit
composer test-feature

# Single test file
docker compose exec -T app php artisan test tests/Feature/Ruc/RucImportV3Test.php

# Single test method
docker compose exec -T app php artisan test tests/Feature/Ruc/RucImportV3Test.php --filter=testStreamingReadsFileInConstantMemory
```

### Code Quality
```bash
# Lint check (Pint)
composer lint

# Lint fix
composer lint-fix

# Static analysis (PHPStan)
composer analyse

# All checks
composer check
```

### Database
```bash
# Create/run migrations
docker compose exec -T app php artisan migrate

# Rollback last migration
docker compose exec -T app php artisan migrate:rollback

# Seed data
docker compose exec -T app php artisan db:seed

# Reset (drop all, re-migrate, re-seed)
docker compose exec -T app php artisan migrate:fresh --seed
```

### Deployment
```bash
# Quick deploy (recommended)
git pull origin main
./update.sh

# Manual deploy (if needed)
docker compose build
docker compose up -d
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan queue:restart
docker compose exec -T app php artisan optimize:clear
```

### Versioning (Semantic)
```bash
# Automatic detection via git hook (on commit with feat:/fix:/BREAKING:)
git commit -m "feat: add streaming support"
# Hook detects → suggests: php artisan app:bump-version minor

# Manual bump
php artisan app:bump-version minor --reason="RUC v3.0 release"
# Updates: composer.json, config/version.php, CHANGELOG.md
```

### Debugging
```bash
# Tail logs
docker compose logs -f app

# Access Tinker REPL
docker compose exec app php artisan tinker

# Check health
docker compose ps
curl http://127.0.0.1:5680/healthz  # CodeRED Agent

# Database diagnostics
docker compose exec -T postgres psql -U codered -d codered -c "\dt"
```

---

## Database Schema Highlights

### Core Tables
- `users` — Platform users (roles, permissions)
- `roles`, `permissions`, `role_has_permissions` — RBAC
- `api_token_requests` — Token solicitation flow (pending → approved → delivered)
- `otp_validations` — OTP codes (hashed with bcrypt)
- `api_token_request_audit_logs` — Audit trail for token requests

### RUC Import (v3.0)
- `ruc_imports` — Import metadata (status, progress, stats)
- `ruc_import_events` — Event sourcing (every state change)
- `ruc_import_duplicates` — Duplicate detection within file
- `ruc_import_errors` — Per-line validation failures
- `ruc_records` — Actual imported records (UPSERT by RUC)

### Relationship Highlights
```
ruc_imports (1) ──── (n) ruc_import_events
ruc_imports (1) ──── (n) ruc_import_errors
ruc_imports (1) ──── (n) ruc_import_duplicates
api_token_requests (1) ──── (1) otp_validations
api_token_requests (1) ──── (n) api_token_request_audit_logs
```

---

## Environment Variables

### Critical
- `APP_KEY` — Encryption key (generated by `artisan key:generate`)
- `APP_URL` — Base URL for links/redirects
- `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — PostgreSQL
- `REDIS_HOST`, `REDIS_PORT` — Redis
- `MAIL_FROM_ADDRESS` — Sender for OTP emails

### RUC v3.0
- `QUEUE_CONNECTION` — Default queue driver (redis)
- `RUC_IMPORT_QUEUE` — Dedicated queue for RUC imports

### Token Requests / Encryption
- `CIPHER` — Encryption algorithm (AES-256-CBC)
- `BLIND_INDEX_KEY` — For blind-indexed email search

### CodeRED Agent (n8n integration)
- `CODERED_AGENT_ENCRYPTION_KEY` — 64-char hex (generated by setup)
- `CODERED_AGENT_LOCAL_API_TOKEN` — 64-char hex (generated by setup)
- `CODERED_AGENT_PUBLIC_URL` — External URL for agent
- `N8N_ENCRYPTION_KEY`, `N8N_DB_*` — n8n configuration

See `.env.example` and `docs/ENVIRONMENT.md` for complete list.

---

## Key Entry Points

### API Endpoints
- `POST /api/v1/token-requests` — Create token request
- `GET /api/v1/token-requests/{id}/status` — Public status check
- `POST /api/v1/token-requests/{id}/otp/request` — Request OTP
- `POST /api/v1/token-requests/{id}/otp/verify` — Verify OTP code
- `POST /api/v1/token-requests/{id}/reveal` — Reveal approved token
- `POST /admin/ruc/imports` — Create RUC import
- `GET /admin/ruc/imports/{id}/progress` — RUC progress (WebSocket via Livewire)

### Web Routes
- `GET /` — Home
- `GET /solicitar-token` — Public token request form
- `GET /admin` — Admin dashboard (requires auth + admin role)
- `GET /admin/ruc/imports` — RUC import manager

### Queue Jobs
- `ProcessRucImportJobV3` — Main RUC processing (streaming, validation, insert, broadcasting)

### Broadcasting Channels
- `private-ruc-import-{id}` — Real-time progress for specific import

---

## Important Conventions

### Code Style
- **Strict types:** All files use `declare(strict_types=1);`
- **Type hints:** 100% typed, no mixed or untyped returns
- **Enum over strings:** Status, merge strategy, etc. use PHP enums
- **Snake case:** Database columns, env vars; PascalCase: classes, namespaces

### Database Migrations
- **Idempotent:** Always check `Schema::hasTable()` / `Schema::hasColumn()` before creating/altering
- **No destructive changes in production:** Rollback and new migration, not .change()
- **Index on foreign keys:** Automatic via `constrained()`

### Git Workflow
- **Conventional commits:** `feat:`, `fix:`, `docs:`, `BREAKING CHANGE:`
- **Auto-version bumping:** Hook detects type → suggests `php artisan app:bump-version {major|minor|patch}`
- **Semantic versioning:** major.minor.patch based on change type
- **Changelog updates:** Automatic via version bump command

### Authorization
- **Gate::authorize():** Use policies for model actions (`can('create', RucImport::class)`)
- **Permissions in DB:** Roles have permissions; users have roles
- **Audit logging:** All sensitive actions logged to audit tables

### Testing
- **Feature tests hit DB:** Use SQLite in-memory (`:memory:`) or test database
- **Factories for seed data:** `RucImportFactory::new()->count(10)->create()`
- **Test isolation:** Each test runs in transaction (rolled back after)

---

## Performance Considerations

### Memory
- RUC v3.0 uses streaming → O(1) memory (no array buffering)
- Livewire hydration is large; use `#[Locked]` for sensitive data (not re-rendered)

### Database
- Indices on `ruc_imports.status`, `ruc_import_events.event_type`, `email_hmac` (blind index)
- Batch inserts via `DB::table()->insertOrIgnore()` (PostgreSQL ON CONFLICT)
- Row-level locking (`lockForUpdate()`) for race condition prevention in token revelation

### Queue
- RUC imports run async via queue; don't block request
- Dedicated `ruc-imports` queue prevents other jobs starving
- Broadcasting progress sub-second (if Pusher/Ably; log driver in dev)

---

## Troubleshooting Quick Reference

| Issue | Check |
|-------|-------|
| "column X does not exist" | Migration not run; `docker compose exec -T app php artisan migrate:status` |
| Migration fails | Idempotency: `Schema::hasColumn()` check missing; re-run with `--fresh` if safe |
| Token not visible in UI | By design (Locked attribute); only appears in email/revelation |
| RUC import stalled | Queue worker running? `docker compose logs queue`; check job exception |
| PostgreSQL not healthy | Wait 30s; check `docker compose logs postgres` |
| Rate limiting on OTP | Check `otp.max_attempts` in `config/token-requests.php` (default 5) |

---

## Documentation Navigation

- **Quick start:** `README.md`
- **Installation:** `docs/INSTALL.md`
- **Architecture & design decisions:** `docs/ARCHITECTURE.md`
- **RUC v3.0 (streaming, event sourcing, rollback):** `docs-ruc/IMPLEMENTATION.md`, `docs-ruc/QUICK_START.md`
- **Semantic versioning:** `docs-dev/VERSIONING.md`
- **All docs index:** `docs/DOCUMENTATION_STRUCTURE.md`
- **Security (encryption, audit, tokens):** `docs/SECURITY.md`

---

## When Adding Features

1. **Create a module** if feature is cross-cutting (e.g., new domain)
2. **Use Actions for business logic** (not controller methods)
3. **Add migrations** with idempotency checks
4. **Write Feature tests** (hit DB, assert side effects)
5. **Implement Policy** for authorization
6. **Use broadcasting** if real-time updates needed
7. **Commit with type** (`feat:`, `fix:`) → auto-bumped version
8. **Update CHANGELOG.md** via version bump (manual or automatic)
9. **Run `composer check`** before pushing

---

## Notes for Future Sessions

- **Versionado automático:** El sistema detecta tipos de commit (feat, fix, BREAKING) y sugiere bumps automáticos. Ver `docs-dev/VERSIONING.md` para detalles.
- **Documentación en Español:** Algunos archivos (.md) están traducidos al español de México (no rompe nada, es documentación pura).
- **RUC v3.0 en producción:** Vea `docs-ruc/DEPLOYMENT.md` para pasos exactos de despliegue (incluido `./update.sh`).
- **Métrica de éxito:** RUC v3.0 es 10x más rápido que v2.0 (1K → 10K registros/segundo), 4x menos memoria (512MB → 128MB pico).
