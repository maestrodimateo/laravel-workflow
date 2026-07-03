# Upgrade Guide

## From 1.x to 2.0

Version 2.0 is a security, concurrency and reliability hardening release.
It changes several **default behaviours**, so review the points below before
deploying. Steps are ordered by likelihood of impacting you.

### 0. Run the migrations (required)

Transitions now write human-readable status labels to the `histories` table.
Publish (if needed) and run the migrations, otherwise every transition will
fail with a missing-column SQL error:

```bash
php artisan vendor:publish --tag=workflow-migrations   # if you keep local copies
php artisan migrate
```

### 1. Authentication & authorization are now enabled by default

Previously every route (public API + admin UI) was reachable without
authentication. Now:

- The default middleware is `['api', 'auth']` and `['web', 'auth']`.
- Every route is additionally guarded by a `manage-workflow` **Gate**.

If you do **not** define that Gate, a safe fallback grants access only in the
`local` environment — so **production returns HTTP 403 until you define your
own policy**. Add it in a service provider:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('manage-workflow', fn ($user) => $user->isAdmin());
```

Adjust the guard to your app if you use tokens (publish the config and set
`workflow.routes.middleware` to e.g. `['api', 'auth:sanctum']`).

To disable the authorization layer entirely (not recommended), set
`workflow.authorization.gate` to `null`.

> Note: because `authorization` and `webhook` are new config keys, they apply
> from the package defaults **even if you already published `config/workflow.php`**
> (config merging is shallow). Re-publish the config to see the new keys:
> `php artisan vendor:publish --tag=workflow-config`.

### 2. Webhooks are restricted (SSRF protection)

`WebhookAction` now validates the URL before calling it. Defaults:

- `https` scheme only,
- private / loopback / link-local / reserved IPs are blocked
  (`127.0.0.1`, `10.0.0.0/8`, `169.254.169.254`, …),
- redirects disabled, 5s timeout.

An existing webhook using `http://` or an internal host will now throw
`UnsafeWebhookUrlException` and will not fire. Relax the policy if needed:

```php
// config/workflow.php
'webhook' => [
    'allowed_schemes'      => ['https', 'http'],
    'allowed_hosts'        => ['hooks.internal.example'], // [] = any public host
    'block_private_ranges' => false,                       // allow internal hosts
    'timeout'              => 5,
],
```

### 3. Message content is sanitized on write

Message `content` is now cleaned through an HTML whitelist on every save (and
again when the email is rendered). Tags outside the whitelist are stripped —
notably **`<img>` is removed** and `<table>` is unwrapped (its text is kept,
the structure is not). Event handlers (`onerror`, …) and `javascript:` URLs are
always removed.

If you rely on images or tables in message bodies, extend
`Maestrodimateo\Workflow\Support\HtmlSanitizer` (fork the whitelist) before
re-saving existing messages.

### 4. `transition()` now validates reachability

`transition($basketId)` throws `InvalidTransitionException` when the target
basket is not a defined "next" of the current status (including baskets from
another circuit). Only transitions along configured edges are allowed.

```php
use Maestrodimateo\Workflow\Exceptions\InvalidTransitionException;

try {
    workflow($model)->transition($nextBasketId);
} catch (InvalidTransitionException $e) {
    // not an allowed step
}
```

It also runs inside a single transaction with a pessimistic lock on the
subject row (concurrent transitions are serialized), and any lock held by the
current user is released whether the transition succeeds **or fails** — a
failed transition no longer leaves the model locked.

### 5. `transitionMany()` skips document-required transitions

Bulk transitions do not run transition actions or emit events. To avoid
silently bypassing `RequireDocumentAction`, models whose transition requires a
document are now **skipped** (reported in the `skipped` array with reason
`Requires document validation`) instead of being moved. Transition those one by
one with `transition()`.

### 6. Queued actions retry — make side effects idempotent

`ExecuteTransitionActionJob` now uses `tries = 3` with backoff and a timeout
(configurable under `workflow.actions_queue`). Because queues are
at-least-once, a `QueueableAction`'s `execute()` may run more than once. Make
side effects idempotent (idempotency key, or dedupe on `(subject, from, to)`)
to avoid duplicate emails/webhooks.

```php
// config/workflow.php
'actions_queue' => [
    'tries'   => 3,
    'backoff' => [10, 30, 60],
    'timeout' => 30,
],
```

### 7. Other behavioural changes (non-breaking for most)

- Message template `{{ variables }}` are now substituted when sending emails
  (previously sent literally); HTML values are escaped.
- History `duration_seconds` is now always positive (Carbon 3 sign fix).
  Existing rows are unchanged.
- Message CRUD derives `circuit_id` from the route, not the request body, and
  nested message routes are scoped to their circuit (IDOR fix). Endpoint URLs
  are unchanged.