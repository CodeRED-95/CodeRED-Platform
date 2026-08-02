# Event Bus Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce a retrocompatible enterprise event bus in CodeRED Platform so the platform only publishes events and the CodeRED Agent becomes the delivery layer for n8n and future integrations.

**Architecture:** Add a focused `app/Services/Events` boundary that builds canonical event envelopes, persists dispatch attempts, and sends events to the Agent through a dedicated transport. Preserve existing endpoints and current token request flows while migrating them to emit events through the new bus, then add operator visibility in the admin panel and operational docs.

**Tech Stack:** Laravel 12, PHP 8.3, queue jobs, PostgreSQL, Redis, Laravel logging channels, Blade / Livewire admin UI, PHPUnit / Pest-style feature tests already present in the repo.

## Global Constraints

- Laravel 12.
- PHP 8.3.
- Retrocompatible behavior only.
- No existing endpoints removed.
- No direct platform-to-n8n communication for new event flow.
- No static helpers for the event bus boundary.
- Event IDs must be UUID v7.
- Event timestamps must be UTC ISO8601.
- Event format must remain exactly: `id`, `type`, `version`, `occurred_at`, `tenant`, `source`, `payload`.
- All event types must come from `EventType` constants; no handwritten strings.
- Queue must work in both enabled and disabled modes.
- The Agent remains the delivery layer; the platform only publishes events.
- Documentation must be updated alongside code.
- Final verification must include tests, lint, typecheck, and build-relevant checks where applicable.

---

### Task 1: Map the current token-request and integration flow

**Files:**
- Modify: `app/Http/Controllers/Api/V1/TokenRequestController.php`
- Modify: `app/Http/Controllers/Public/PublicTokenRequestController.php`
- Modify: `app/Listeners/SendTokenRequestCreatedWebhook.php`
- Modify: `app/Jobs/NotifyN8nTokenRequestStatus.php`
- Modify: `app/Services/Integrations/N8nWebhookClient.php`
- Test: `tests/Feature/ApiTokenRequestAdminTest.php`
- Test: `tests/Feature/PublicTokenRequestWebTest.php`
- Test: `tests/Feature/ChromeExtensionTokenRequestTest.php`

**Interfaces:**
- Consumes: existing `TokenRequestCreated` event, current n8n webhook flow, token request persistence.
- Produces: a clear migration path for event publication without removing current behavior yet.

- [ ] **Step 1: Write failing tests that describe the current notification path and the new event publication seam**

```php
public function test_token_request_creation_still_persists_and_can_publish_event_payload(): void
{
    // Assert the request still creates the current records.
    // Assert a canonical event payload can be built from the request without talking to n8n directly.
}
```

- [ ] **Step 2: Run the focused tests and confirm the new expectations fail for the right reason**

Run: `php artisan test tests/Feature/ApiTokenRequestAdminTest.php --filter=token_request_creation_still_persists_and_can_publish_event_payload`

Expected: fail because the new event bus seam does not exist yet.

- [ ] **Step 3: Implement the minimal mapping layer required by the later tasks**

```php
// Keep current controller behavior intact.
// Add only the seams that later tasks will replace with EventDispatcher.
```

- [ ] **Step 4: Re-run the focused tests and confirm they pass**

Run: `php artisan test tests/Feature/ApiTokenRequestAdminTest.php --filter=token_request_creation_still_persists_and_can_publish_event_payload`

Expected: pass without changing the public API contract.

- [ ] **Step 5: Commit the mapping step**

```bash
git add app/Http/Controllers/Api/V1/TokenRequestController.php app/Http/Controllers/Public/PublicTokenRequestController.php app/Listeners/SendTokenRequestCreatedWebhook.php app/Jobs/NotifyN8nTokenRequestStatus.php app/Services/Integrations/N8nWebhookClient.php tests/Feature/ApiTokenRequestAdminTest.php tests/Feature/PublicTokenRequestWebTest.php tests/Feature/ChromeExtensionTokenRequestTest.php
git commit -m "refactor: prepare token request event seams"
```

### Task 2: Build the event bus core

**Files:**
- Create: `app/Services/Events/EventType.php`
- Create: `app/Services/Events/DTO/EventData.php`
- Create: `app/Services/Events/Contracts/EventDispatcherContract.php`
- Create: `app/Services/Events/Contracts/EventTransportContract.php`
- Create: `app/Services/Events/Contracts/EventRepositoryContract.php`
- Create: `app/Services/Events/EventFactory.php`
- Create: `app/Services/Events/EventDispatcher.php`
- Create: `app/Services/Events/Exceptions/EventDispatchException.php`
- Create: `config/events.php`
- Modify: `app/Providers/AppServiceProvider.php` or the existing service provider used for bindings
- Modify: `bootstrap/app.php` only if a new middleware or alias is truly needed
- Test: `tests/Unit/Events/EventFactoryTest.php`
- Test: `tests/Unit/Events/EventDispatcherTest.php`

**Interfaces:**
- Consumes: payload arrays, event type constants, configuration values from `config/events.php`.
- Produces: canonical event envelopes, dispatch services, and a transport abstraction for the Agent.

- [ ] **Step 1: Write failing unit tests for canonical event shape, UUID v7, UTC timestamps, and event type constants**

```php
public function test_it_builds_the_canonical_event_envelope(): void
{
    $event = $factory->make(EventType::TOKEN_REQUEST_CREATED, ['request_id' => 123]);

    $this->assertSame('platform', $event->source);
    $this->assertSame('default', $event->tenant);
    $this->assertSame(1, $event->version);
    $this->assertSame('token.request.created', $event->type);
}
```

- [ ] **Step 2: Run the unit tests and confirm the missing classes fail**

Run: `php artisan test tests/Unit/Events/EventFactoryTest.php tests/Unit/Events/EventDispatcherTest.php`

Expected: fail because the bus classes do not exist yet.

- [ ] **Step 3: Implement the smallest complete event factory and dispatcher**

```php
// EventFactory builds the envelope.
// EventDispatcher validates enablement, persists dispatch intent, and delegates to a transport.
```

- [ ] **Step 4: Bind interfaces through the container and load `config/events.php`**

```php
// Use constructor injection only.
// No static helpers.
```

- [ ] **Step 5: Re-run the unit tests**

Run: `php artisan test tests/Unit/Events/EventFactoryTest.php tests/Unit/Events/EventDispatcherTest.php`

Expected: pass.

- [ ] **Step 6: Commit the event core**

```bash
git add app/Services/Events config/events.php app/Providers/AppServiceProvider.php tests/Unit/Events
git commit -m "feat: add canonical event bus core"
```

### Task 3: Persist dispatches and deliver to the Agent

**Files:**
- Create: `database/migrations/xxxx_xx_xx_create_event_dispatches_table.php`
- Create: `app/Models/EventDispatch.php`
- Create: `app/Services/Events/DTO/EventDispatchRecord.php`
- Create: `app/Services/Events/Contracts/EventDeliveryContract.php`
- Create: `app/Services/Events/AgentEventTransport.php`
- Create: `app/Jobs/DispatchPlatformEventJob.php`
- Modify: `config/logging.php`
- Modify: `config/services.php`
- Modify: `app/Services/Integrations/N8nWebhookClient.php` only if the current client can be reused safely as a low-level HTTP building block
- Test: `tests/Feature/EventDispatchPersistenceTest.php`
- Test: `tests/Feature/EventDispatchRetryTest.php`

**Interfaces:**
- Consumes: canonical event DTOs from Task 2.
- Produces: `event_dispatches` rows, retry bookkeeping, Agent POST delivery, and event logs.

- [ ] **Step 1: Write failing feature tests for persistence, retry accounting, and queue/no-queue behavior**

```php
public function test_it_persists_event_dispatches_before_delivery(): void
{
    // Assert the event_dispatches row exists with status, attempts, and payload snapshot.
}
```

- [ ] **Step 2: Run the feature tests and confirm they fail**

Run: `php artisan test tests/Feature/EventDispatchPersistenceTest.php tests/Feature/EventDispatchRetryTest.php`

Expected: fail because the table, model, and job do not exist yet.

- [ ] **Step 3: Add the migration, model, transport, and queue job**

```php
// The job retries delivery and never drops the event without recording the failure.
// Queue enabled => dispatch job.
// Queue disabled => dispatch synchronously through the same transport contract.
```

- [ ] **Step 4: Add the dedicated `events` log channel and Agent endpoint config**

```php
// Keep logs out of laravel.log.
// Reuse config/services.php for Agent base URL and auth token if already present.
```

- [ ] **Step 5: Re-run the feature tests**

Run: `php artisan test tests/Feature/EventDispatchPersistenceTest.php tests/Feature/EventDispatchRetryTest.php`

Expected: pass.

- [ ] **Step 6: Commit the persistence layer**

```bash
git add database/migrations app/Models app/Services/Events config/logging.php config/services.php app/Jobs/DispatchPlatformEventJob.php tests/Feature/EventDispatchPersistenceTest.php tests/Feature/EventDispatchRetryTest.php
git commit -m "feat: persist and retry platform events"
```

### Task 4: Emit the first platform events

**Files:**
- Modify: `app/Http/Controllers/Api/V1/TokenRequestController.php`
- Modify: `app/Http/Controllers/Public/PublicTokenRequestController.php`
- Modify: `app/Http/Controllers/Api/V1/TokenRequestApprovalController.php` or the actual approval/rejection controller used in the repo
- Modify: `app/Listeners/SendTokenRequestCreatedWebhook.php`
- Test: `tests/Feature/ApiTokenRequestAdminTest.php`
- Test: `tests/Feature/PublicTokenRequestWebTest.php`
- Test: `tests/Feature/ApiTokenRequestAdminTest.php`

**Interfaces:**
- Consumes: `EventDispatcherContract`.
- Produces: `TOKEN_REQUEST_CREATED`, `TOKEN_REQUEST_APPROVED`, and `TOKEN_REQUEST_REJECTED` emissions with the canonical payload shape.

- [ ] **Step 1: Write failing tests for the three initial event types**

```php
public function test_token_request_approval_dispatches_the_approved_event(): void
{
    // Assert the dispatcher receives EventType::TOKEN_REQUEST_APPROVED.
}
```

- [ ] **Step 2: Run the feature tests and confirm they fail**

Run: `php artisan test tests/Feature/ApiTokenRequestAdminTest.php tests/Feature/PublicTokenRequestWebTest.php`

Expected: fail because the new dispatch calls are not wired yet.

- [ ] **Step 3: Inject the dispatcher into the relevant controllers and listeners**

```php
// Keep the existing endpoint responses intact.
// Replace direct webhook emission with platform event publication where the flow is ready.
```

- [ ] **Step 4: Re-run the feature tests**

Run: `php artisan test tests/Feature/ApiTokenRequestAdminTest.php tests/Feature/PublicTokenRequestWebTest.php`

Expected: pass.

- [ ] **Step 5: Commit the platform event emission**

```bash
git add app/Http/Controllers/Api/V1/TokenRequestController.php app/Http/Controllers/Public/PublicTokenRequestController.php app/Listeners/SendTokenRequestCreatedWebhook.php tests/Feature/ApiTokenRequestAdminTest.php tests/Feature/PublicTokenRequestWebTest.php
git commit -m "feat: publish initial token request events"
```

### Task 5: Add administration, dashboard, docs, and versioning

**Files:**
- Create or modify: `app/Livewire/Admin/Events/*` or the actual admin stack used in the repo
- Create or modify: `resources/views/livewire/admin/events/*`
- Modify: `routes/web.php`
- Modify: existing dashboard widget classes and tests
- Create: `docs/events.md`
- Modify: `README.md`
- Modify: `CHANGELOG.md`
- Modify: `.env.example`
- Modify: `Install_CodeRED-Platform.sh`
- Modify: `update.sh`
- Modify: `CodeRED.sh`
- Modify: `config/version.php` and any other central version source
- Test: `tests/Feature/EventDispatchAdminPagesTest.php`
- Test: `tests/Feature/EventDashboardWidgetsTest.php`
- Test: `tests/Feature/SystemVersionTest.php`

**Interfaces:**
- Consumes: persisted event dispatch records and the dispatcher transport state.
- Produces: admin visibility, retry controls, search/filter detail views, user-facing docs, and a consistent version bump.

- [ ] **Step 1: Write failing UI and docs tests for listing, filtering, detail, retry, and version consistency**

```php
public function test_events_admin_page_lists_event_dispatches(): void
{
    // Assert the admin page shows date, type, status, attempts, duration, response, and error.
}
```

- [ ] **Step 2: Run those tests and confirm they fail**

Run: `php artisan test tests/Feature/EventDispatchAdminPagesTest.php tests/Feature/EventDashboardWidgetsTest.php tests/Feature/SystemVersionTest.php`

Expected: fail because the admin surface and docs/version updates are not present yet.

- [ ] **Step 3: Implement the admin page, widgets, docs, scripts, and version bump**

```php
// Add retry and detail actions.
// Add today's sent, failed, average time, pending, and recent widgets.
// Bump the platform version consistently wherever the repo stores it.
```

- [ ] **Step 4: Re-run the UI and version tests**

Run: `php artisan test tests/Feature/EventDispatchAdminPagesTest.php tests/Feature/EventDashboardWidgetsTest.php tests/Feature/SystemVersionTest.php`

Expected: pass.

- [ ] **Step 5: Run the full quality gate**

Run:
`php artisan test`
`./vendor/bin/phpstan analyse`
`composer lint`
`composer verify`

Expected: no warnings, no deprecations, no failures.

- [ ] **Step 6: Commit the final delivery**

```bash
git add app resources routes config docs README.md CHANGELOG.md .env.example Install_CodeRED-Platform.sh update.sh CodeRED.sh tests
git commit -m "feat: add enterprise platform event bus"
```

## Self-Review

- Coverage check:
  - Event envelope and constants: Task 2.
  - Persistence, retry, and queue behavior: Task 3.
  - Initial token request events: Task 4.
  - Admin dashboard, docs, scripts, and versioning: Task 5.
  - Retrocompatibility and existing endpoint preservation: Tasks 1 and 4.
- Placeholder scan:
  - No TBD/TODO placeholders.
  - No undefined function names in later tasks.
  - No vague verification steps.
- Type consistency:
  - `EventDispatcherContract`, `EventTransportContract`, `EventRepositoryContract`, and `EventDeliveryContract` are introduced before use.
  - `EventType` constants are referenced consistently across tasks.
  - Version bump is explicitly called out as a platform-wide consistency step.

