# Token Request Telegram Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Notify n8n/Telegram automatically when a new API token request is created, using a post-commit domain event, queued webhook job, signed HMAC payload, idempotent delivery records, admin retry, version bump, and documentation.

**Architecture:** Token request creation fires `TokenRequestCreated` after commit. A queued listener/job creates or reuses a `webhook_deliveries` record and uses `N8nWebhookClient` to POST a minimized signed payload to n8n. Admin UI reads delivery records and can dispatch a retry without exposing secrets.

**Tech Stack:** Laravel 12, Eloquent, queued listeners/jobs, HTTP client, Livewire admin panel, PHPUnit, Laravel queues, n8n webhook, Telegram workflow documentation.

## Global Constraints

- Do not send Telegram directly from controllers.
- Do not expose tokens, secrets, signatures, full contacts, IP, user agent, or internal notes in webhook payload/logs.
- Fire notifications only after database commit.
- If n8n fails, token request creation must still succeed.
- Use `Http::fake()` in tests.
- Version bump: `2.1.0`.

---

### Task 1: Event And Dispatch Points

**Files:**
- Create: `app/Events/TokenRequestCreated.php`
- Modify: `app/Http/Controllers/Public/PublicTokenRequestController.php`
- Modify: `app/Http/Controllers/Api/V1/TokenRequestController.php`
- Modify: `app/Http/Controllers/Api/V1/Integrations/N8nTokenRequestController.php`
- Test: `tests/Feature/TokenRequestCreatedNotificationTest.php`

**Interfaces:**
- Produces: `TokenRequestCreated::__construct(ApiTokenRequest $tokenRequest)`, public `$afterCommit = true`.

- [ ] Write failing tests that token request creation dispatches `TokenRequestCreated` and keeps creating request when notifications are disabled.
- [ ] Run focused tests and confirm failure.
- [ ] Create event and replace created-dispatch calls with `event(new TokenRequestCreated($tokenRequest))`.
- [ ] Run focused tests and confirm pass.

### Task 2: Webhook Delivery Persistence And Client

**Files:**
- Create migration for `webhook_deliveries`.
- Create: `app/Models/WebhookDelivery.php`
- Create: `app/Services/Integrations/N8nWebhookClient.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Test: `tests/Feature/TokenRequestCreatedNotificationTest.php`

**Interfaces:**
- Produces: `N8nWebhookClient::sendTokenRequestCreated(WebhookDelivery $delivery, ApiTokenRequest $request): int`.
- Produces payload with `event`, `event_id`, `occurred_at`, and minimized `request` object.

- [ ] Write failing tests for signed payload, masked contact, no full contact/token, disabled config, and successful delivery record.
- [ ] Run focused tests and confirm failure.
- [ ] Implement migration/model/config/client.
- [ ] Run focused tests and confirm pass.

### Task 3: Queued Listener/Job And Manual Retry

**Files:**
- Create: `app/Listeners/SendTokenRequestCreatedWebhook.php`
- Modify: `app/Providers/AppServiceProvider.php` or existing event registration.
- Modify: `app/Livewire/Admin/ApiTokenRequests/Index.php`
- Modify related Blade view under `resources/views/livewire/admin/api-token-requests/`.
- Test: `tests/Feature/TokenRequestCreatedNotificationTest.php` and `tests/Feature/ApiTokenRequestAdminTest.php`.

**Interfaces:**
- Listener implements `ShouldQueue`, `tries = 5`, `backoff(): array`, `timeout`.
- Admin method `retryCreatedNotification(int $requestId): void` dispatches listener/job after permission check.

- [ ] Write failing tests for queued listener, retries, 500 behavior, manual retry permissions, and UI notification section.
- [ ] Run focused tests and confirm failure.
- [ ] Implement listener registration and admin retry/UI.
- [ ] Run focused tests and confirm pass.

### Task 4: Version And Documentation

**Files:**
- Modify: `config/version.php`, `composer.json`, `composer.lock`, extension versions if applicable.
- Modify: `README.md`, `docs/CHANGELOG.md`, `docs/ENVIRONMENT.md`, `docs/integrations/n8n-telegram-token-requests.md`, `docs/SECURITY.md`.
- Test: `tests/Feature/SystemVersionTest.php`.

**Interfaces:**
- Version returned everywhere is `2.1.0`.

- [ ] Write failing version/doc assertions.
- [ ] Run focused tests and confirm failure.
- [ ] Update version/docs.
- [ ] Run focused tests and confirm pass.

### Task 5: Final Verification

- [ ] Run extension tests/build validations if extension touched.
- [ ] Run platform focused tests.
- [ ] Run `composer lint`, `composer analyse`, `composer validate --strict`.
- [ ] Run `composer verify` and document any unrelated failures.
- [ ] Clean generated caches from git status.
