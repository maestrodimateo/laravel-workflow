# Laravel Workflow

[![Latest Version on Packagist](https://img.shields.io/packagist/v/maestrodimateo/laravel-workflow.svg)](https://packagist.org/packages/maestrodimateo/laravel-workflow)
[![License](https://img.shields.io/packagist/l/maestrodimateo/laravel-workflow.svg)](https://packagist.org/packages/maestrodimateo/laravel-workflow)

A visual, configurable workflow engine for Laravel.
Define circuits (workflows), baskets (steps) and transitions, then move any Eloquent model through them with a clean Facade API.

Ships with a **built-in visual admin interface** to design your workflows by drag-and-drop.

---

## Table of contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Key concepts](#key-concepts)
- [Facade API](#facade-api)
- [The Workflowable trait](#the-workflowable-trait)
- [Multi-circuit](#multi-circuit)
- [Resource locking](#resource-locking)
- [Transition actions](#transition-actions)
- [Transition conditions](#transition-conditions)
- [Duration tracking](#duration-tracking)
- [Message templates](#message-templates)
- [Events](#events)
- [Export & Import](#export--import)
- [Admin interface](#admin-interface)
- [Configuration](#configuration)
- [Testing](#testing)
- [License](#license)

---

## Features

| Category | Description |
|---|---|
| Visual designer | Drag-and-drop baskets, draw transitions, configure actions & conditions |
| Facade & helper | `Workflow::for($model)->transition($id)` or `workflow($model)->transition($id)` |
| Multi-circuit | A model can belong to multiple workflows simultaneously |
| Resource locking | Prevent concurrent access with `lock()` / `unlock()` |
| Role-based access | Allowed roles per circuit and per basket |
| Transition actions | Email, webhook, log, required documents, custom actions on each transition |
| Transition conditions | Gate a transition on the model's state (server-side + UI) |
| Duration tracking | Automatic timing between steps |
| Full history | Every transition is logged: who, when, how long, why |
| Message templates | WYSIWYG editor with variable interpolation |
| Export / Import | Share workflows as JSON + PNG image export |
| Dark mode | Light and dark themes |
| No CDN | Vendored assets, served same-origin, works offline |

---

## Requirements

- PHP 8.3+
- Laravel 12 or 13

---

## Installation

```bash
composer require maestrodimateo/laravel-workflow
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=workflow-config
php artisan vendor:publish --tag=workflow-migrations
php artisan migrate
```

Optionally publish the views to customize the admin UI:

```bash
php artisan vendor:publish --tag=workflow-views
```

---

## Quick start

### 1. Add the trait to your model

```php
use Maestrodimateo\Workflow\Traits\Workflowable;

class Invoice extends Model
{
    use HasUuids, Workflowable;
}
```

When an `Invoice` is created, it is automatically placed in the **DRAFT** basket of every circuit targeting it.

### 2. Open the visual designer

Navigate to `/workflow/admin`. From there you can:

1. Create a **circuit** targeting your model
2. Add **baskets** (steps) with colors and roles
3. Draw **transitions** by dragging from one basket to another
4. Configure **actions** and **conditions** on each transition

### 3. Transition models in your code

```php
use Maestrodimateo\Workflow\Facades\Workflow;

// Current status
$basket = Workflow::for($invoice)->currentStatus();
$basket->name;   // "Draft"
$basket->status; // "DRAFT"

// Available next steps
$options = Workflow::for($invoice)->nextBaskets();

// Transition
Workflow::for($invoice)->transition($nextBasket->id, 'Approved by manager');
```

The `workflow()` helper is also available:

```php
workflow($invoice)->currentStatus();
workflow($invoice)->transition($basketId);
```

---

## Key concepts

The package is built around three concepts:

```
Circuit (workflow)
 └── Basket (step)
      └── Transition (directed link between two baskets)
           ├── Actions    (what happens when the model moves)
           └── Conditions (what prevents the model from moving)
```

| Concept | Description | Example |
|---|---|---|
| **Circuit** | A complete workflow targeting an Eloquent model | "Invoice approval" |
| **Basket** | A step in the circuit, with a status, color and roles | "Pending review" |
| **Transition** | A directed link between two baskets, carrying actions and conditions | Draft &rarr; Review |

---

## Facade API

All methods are available via `Workflow::` or `workflow()->`.

### Model-bound methods

Always prefixed with `Workflow::for($model)`:

```php
$wf = Workflow::for($model);
```

**Navigation:**

| Method | Returns | Description |
|---|---|---|
| `in($circuit)` | `WorkflowManager` | Scope to a specific circuit |
| `currentStatus()` | `?Basket` | Current basket of the model |
| `nextBaskets()` | `Collection` | Baskets reachable from the current one |
| `availableTransitions()` | `array` | Next baskets with `open` (bool) and `blockedBy` (reasons) |
| `allStatuses()` | `array` | Current basket in every circuit |
| `circuits()` | `Collection` | All circuits the model belongs to |

**Actions:**

| Method | Returns | Description |
|---|---|---|
| `transition($id, $comment)` | `bool` | Move the model to the target basket |
| `lock($minutes)` | `WorkflowLock` | Lock the model for exclusive access |
| `unlock($force)` | `void` | Release the lock |

**Queries:**

| Method | Returns | Description |
|---|---|---|
| `history()` | `Collection` | Full transition history |
| `totalDuration()` | `int` | Total processing time in seconds |
| `durationInStatus($status)` | `int` | Time spent in a specific status (seconds) |
| `isLocked()` | `bool` | Is the model locked? |
| `isLockedByMe()` | `bool` | Locked by the current user? |
| `lockedBy()` | `?string` | User ID holding the lock |
| `lockExpiration()` | `?Carbon` | When the lock expires |
| `requiredDocuments($basketId)` | `array` | Documents required for a transition |
| `requirements()` | `array` | All requirements for all next transitions |

### Static methods

No `for()` needed:

| Method | Returns | Description |
|---|---|---|
| `importFromJson($path)` | `Circuit` | Import a circuit from an exported JSON file |
| `registerAction($class)` | `void` | Register a custom transition action |
| `getRegisteredActions()` | `array` | List all registered action classes |
| `registerCondition($class)` | `void` | Register a custom transition condition |
| `getRegisteredConditions()` | `array` | List all registered condition classes |

### Role-based queries

```php
// Circuits accessible to a role
Workflow::circuitsForRole('manager');
Workflow::circuitsForRoles(['admin', 'manager']);

// Baskets accessible to a role
Workflow::basketsForRole('validator');
Workflow::basketsForRole('validator', $circuitId);
Workflow::basketsForRoles(['admin', 'operator'], $circuitId);
```

Equivalent Eloquent scopes:

```php
Circuit::forRole('admin')->get();
Basket::forRoles(['admin', 'manager'])->get();

$basket->hasRole('validator');  // true/false
$circuit->hasRole('admin');     // true/false
```

---

## The Workflowable trait

The trait adds relations, methods and scopes directly on your model.

### Relations

```php
$invoice->baskets;        // All baskets (past and current)
$invoice->histories;      // Transition history
$invoice->workflowLock;   // Active lock (or null)
```

### Methods

```php
// Current status
$invoice->currentStatus();              // Across all circuits
$invoice->currentStatus($circuit);      // In a specific circuit

// Inspect the basket
$basket = $invoice->currentStatus();
$basket->status;   // "REVIEW"
$basket->name;     // "Under Review"
$basket->color;    // "#2563eb"
$basket->roles;    // ["manager", "validator"]
$basket->next;     // Collection<Basket> — possible next steps
$basket->previous; // Collection<Basket> — where it came from
```

### Scopes

```php
// Models in a specific basket
Invoice::fromBasket($reviewBasket)->get();

// Unlocked models
Invoice::unlocked()->get();

// Models locked by a specific user
Invoice::lockedBy(auth()->id())->get();

// Combine scopes
Invoice::fromBasket($reviewBasket)->unlocked()->get();
```

### Automatic behavior

When a model is created, it is attached to the DRAFT basket of **every** circuit targeting its class:

```php
$invoice = Invoice::create(['number' => 'INV-001']);

$invoice->currentStatus()->status; // "DRAFT"
$invoice->baskets->count();        // 1 (or more if multiple circuits)
```

---

## Multi-circuit

A model can belong to multiple circuits at the same time.

Use `in()` to scope operations:

```php
Workflow::for($invoice)->in($approvalCircuit)->currentStatus();
Workflow::for($invoice)->in($complianceCircuit)->transition($basketId);
Workflow::for($invoice)->in('circuit-uuid')->history();
```

See all statuses at once:

```php
$statuses = Workflow::for($invoice)->allStatuses();
// [
//     'circuit-a-id' => ['circuit' => Circuit, 'basket' => Basket],
//     'circuit-b-id' => ['circuit' => Circuit, 'basket' => Basket],
// ]
```

---

## Resource locking

Prevent multiple operators from working on the same model simultaneously.

### Usage

```php
// Lock (default: 30 minutes)
Workflow::for($invoice)->lock();
Workflow::for($invoice)->lock(60);  // 1 hour

// Check
Workflow::for($invoice)->isLocked();
Workflow::for($invoice)->isLockedByMe();
Workflow::for($invoice)->lockedBy();        // "user-uuid"
Workflow::for($invoice)->lockExpiration();  // Carbon

// Transition — automatically checks the lock
Workflow::for($invoice)->transition($basketId);
// OK if you hold the lock (released after transition)
// ModelLockedException if locked by someone else

// Release
Workflow::for($invoice)->unlock();
Workflow::for($invoice)->unlock(force: true);  // admin
```

### Handling lock exceptions

```php
use Maestrodimateo\Workflow\Exceptions\ModelLockedException;

try {
    Workflow::for($invoice)->transition($basketId);
} catch (ModelLockedException $e) {
    // "This resource is locked by [user] until [14:30]."
}
```

### Configuration

```env
WORKFLOW_LOCK_DURATION=30  # minutes
```

---

## Transition actions

Actions execute automatically when a transition occurs. They are **configured visually** in the designer.

### Built-in actions

| Action | Key | Config | Execution |
|---|---|---|---|
| Send email | `send_email` | Select a message from the circuit | Queued (after commit) |
| Webhook | `webhook` | Target URL | Queued (after commit) |
| Log | `log` | Optional message | After commit |
| Require documents | `require_document` | List of documents (type + label) | In transaction |

### Execution modes

Every `transition()` is wrapped in a DB transaction. Actions run in one of three modes:

| Mode | Interface | When to use |
|---|---|---|
| **In transaction** (default) | — | Validations, atomic DB writes. An exception rolls back the transition. |
| **After commit** | `AfterCommitAction` | Fast, non-rollbackable side effects (log, cache). |
| **Queued** | `QueueableAction` | Slow or external side effects (email, HTTP, APIs). The request returns immediately. |

### Creating a custom action

```bash
php artisan make:workflow-action GeneratePdfAction
```

This creates `app/Workflow/Actions/GeneratePdfAction.php`:

```php
use Maestrodimateo\Workflow\Contracts\TransitionAction;

class GeneratePdfAction implements TransitionAction
{
    public static function key(): string   { return 'generate_pdf'; }
    public static function label(): string { return 'Generate PDF'; }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        // Your logic here
    }
}
```

Register it in your `AppServiceProvider::boot()`:

```php
Workflow::registerAction(GeneratePdfAction::class);
```

The action immediately appears in the designer's "Add action" menu on any transition.

### After-commit action

For non-rollbackable external side effects:

```php
use Maestrodimateo\Workflow\Contracts\AfterCommitAction;
use Maestrodimateo\Workflow\Contracts\TransitionAction;

class NotifySlackAction implements TransitionAction, AfterCommitAction
{
    public static function key(): string   { return 'notify_slack'; }
    public static function label(): string { return 'Notify Slack'; }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        Http::post($config['webhook_url'], ['model' => $model->getKey(), 'to' => $to->status]);
    }
}
```

### Queued action

For slow side effects — the request returns immediately:

```php
use Maestrodimateo\Workflow\Contracts\QueueableAction;
use Maestrodimateo\Workflow\Contracts\TransitionAction;

class NotifySlackAction implements TransitionAction, QueueableAction
{
    public static function key(): string       { return 'notify_slack'; }
    public static function label(): string     { return 'Notify Slack'; }
    public static function queue(): ?string    { return 'notifications'; }
    public static function connection(): ?string { return null; }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        Http::post($config['webhook_url'], ['model' => $model->getKey(), 'to' => $to->status]);
    }
}
```

What you get for free:

- **Race-free dispatch** — the job is sent via `DB::afterCommit()`, so the worker never sees an intermediate state
- **Fresh state** — the model is re-fetched from the DB when the job runs
- **Automatic retries** — handled by the Laravel queue worker like any other job

Global configuration:

```env
WORKFLOW_ACTIONS_QUEUE=workflow
WORKFLOW_ACTIONS_QUEUE_CONNECTION=redis
```

> With `QUEUE_CONNECTION=sync`, queued actions run inline. Switching to `redis` or `database` is enough to offload them — no code change needed.

> A `QueueableAction` already runs after commit — no need to also implement `AfterCommitAction`.

---

## Transition conditions

Gate a transition on the model's own state. Conditions are evaluated in **two places** with the same result:

- **Server-side** — the transition is rejected with a rollback
- **Your UI** — `availableTransitions()` tells you which transitions are open or blocked

### Built-in: attribute condition (no code)

In the designer, open a transition and add **Condition &rarr; Model attribute**.
Pick a field, an operator and a value.

| Operator | Meaning |
|---|---|
| `=` `!=` | Equals / not equals |
| `<` `<=` `>` `>=` | Comparisons |
| `in` `not_in` | Value in a comma-separated list |
| `empty` `not_empty` | Attribute is blank / present |
| `contains` | String contains |

Example: only allow *Draft &rarr; Approved* when `amount <= 1000`.

### Custom condition

For anything the attribute editor can't express:

```php
use Maestrodimateo\Workflow\Contracts\TransitionCondition;

class BudgetApprovedCondition implements TransitionCondition
{
    public static function key(): string   { return 'budget_approved'; }
    public static function label(): string { return 'Budget approved'; }

    public function passes(Model $model, array $config = []): bool
    {
        return $model->budget?->is_approved === true;
    }

    public function reason(array $config = []): string
    {
        return 'The budget must be approved first.';
    }

    // Optional: limit this condition to specific models
    public static function models(): array
    {
        return [\App\Models\Invoice::class];
    }
}
```

Register it in `config/workflow.php`:

```php
'conditions' => [
    App\Workflow\Conditions\BudgetApprovedCondition::class,
],
```

### Application side

```php
// Check which transitions are available
foreach (Workflow::for($invoice)->availableTransitions() as $t) {
    $t['basket'];     // Target basket
    $t['label'];      // Transition label
    $t['open'];       // true/false
    $t['blockedBy'];  // ["The budget must be approved first."]
}

// Handle rejection
try {
    Workflow::for($invoice)->transition($basketId);
} catch (\Maestrodimateo\Workflow\Exceptions\TransitionConditionException $e) {
    $e->reasons;  // ["The budget must be approved first."]
}
```

> Conditions must be read-only (no side effects).

---

## Duration tracking

Every transition automatically records the time spent in the previous step.

```php
$history = Workflow::for($invoice)->history();

foreach ($history as $entry) {
    $entry->previous_status;  // "DRAFT"
    $entry->next_status;      // "REVIEW"
    $entry->duration_seconds; // 3600
    $entry->duration_human;   // "1h"
    $entry->done_by;          // User ID
    $entry->comment;          // "Sent for review"
}

// Total time
Workflow::for($invoice)->totalDuration();             // seconds

// Time in a specific step
Workflow::for($invoice)->durationInStatus('REVIEW');  // seconds
```

Human-readable formats: `45s`, `12min`, `2h 35min`, `3d 4h`.

---

## Message templates

Messages are created at the circuit level and used in transition actions (`send_email`).

The WYSIWYG editor supports **variable interpolation**:

```
Hello, request {{ reference }} has been moved from {{ from_name }}
to {{ to_name }} by {{ user }} on {{ datetime }}.
```

### Built-in variables

| Variable | Description |
|---|---|
| `{{ model_id }}` | Model identifier |
| `{{ model_type }}` | Model class name |
| `{{ from_status }}` / `{{ from_name }}` | Source basket |
| `{{ to_status }}` / `{{ to_name }}` | Target basket |
| `{{ circuit_name }}` | Circuit name |
| `{{ date }}` / `{{ heure }}` / `{{ datetime }}` | Current date/time |
| `{{ user }}` | User performing the transition |

### Custom variables

```php
// config/workflow.php
'message_variables' => [
    'reference' => fn ($model) => $model->reference,
    'amount'    => fn ($model) => number_format($model->amount, 2, '.', ','),
],
```

---

## Events

A `TransitionEvent` is fired after every transition:

```php
// EventServiceProvider
protected $listen = [
    \Maestrodimateo\Workflow\Events\TransitionEvent::class => [
        \App\Listeners\NotifySlack::class,
    ],
];
```

```php
public function handle(TransitionEvent $event): void
{
    $event->currentBasket; // Source basket
    $event->nextBasket;    // Target basket
    $event->model;         // The transitioned model
    $event->comment;       // Transition comment
}
```

### What happens during a transition

```
1. Lock guard (ModelLockedException if locked by another user)

2. DB transaction
   a. Model detached from current basket, attached to next
   b. In-transaction actions (e.g. require_document — may throw and rollback)
   c. TransitionEvent → HistoryListener records history + duration
   d. Lock released

3. Commit

4. After-commit actions (log, AfterCommitAction)
5. Queued actions (send_email, webhook, QueueableAction)
6. Your custom listeners
```

---

## Export & Import

### From the admin UI

- **Export JSON** — download the full circuit definition
- **Export PNG** — download a high-resolution image of the workflow diagram
- **Import** — select a `.json` file to recreate a circuit

### Via API

```
GET  /workflow/admin/api/circuits/{circuit}/export
POST /workflow/admin/api/circuits/import  (multipart, field: "file")
```

### Programmatic import (seeders, commands)

```php
use Maestrodimateo\Workflow\Facades\Workflow;

Workflow::importFromJson(database_path('seeders/workflow-invoices.json'));
```

Creates the full circuit (baskets, transitions, messages) inside a DB transaction.
Returns the `Circuit` instance with all relations loaded.
Throws `\InvalidArgumentException` if the file is invalid.

---

## Admin interface

The visual designer is available at `/workflow/admin`.

| Feature | Details |
|---|---|
| Circuits | Create, edit, delete, assign roles |
| Canvas | Drag-and-drop baskets, panning, auto-layout |
| Linking | Click the output port then click the target basket |
| Transitions | Click a link to configure label, actions, conditions |
| Baskets | `Delete` / `Backspace` to remove the selected basket |
| Messages | WYSIWYG editor with variables |
| Export / Import | JSON + PNG export, JSON import |
| Zoom | Scroll wheel + controls |
| Theme | Light / dark |

### Front-end assets

Libraries (compiled Tailwind, Alpine.js, Quill) are vendored under `resources/dist/` and served **same-origin** — no CDN. The UI works offline and under a strict CSP.

Only maintainers editing the Blade views need Node:

```bash
npx tailwindcss@3 -c tailwind.config.js -i resources/css/input.css -o resources/dist/app.css --minify
```

---

## Configuration

```php
// config/workflow.php
return [
    // Routes & middleware
    'routes' => [
        'prefix'           => 'workflow',
        'middleware'        => ['api', 'auth'],
        'admin_middleware'  => ['web', 'auth'],
    ],

    // Authorization gate (define in a ServiceProvider)
    // Gate::define('manage-workflow', fn ($user) => $user->isAdmin());
    'authorization' => [
        'gate' => 'manage-workflow',
    ],

    // User attribute stored in history
    'auth_identifier' => 'id',

    // Custom variables for message templates
    'message_variables' => [],

    // Custom actions & conditions
    'actions'    => [],
    'conditions' => [],

    // Queue settings for async actions
    'actions_queue' => [
        'queue'      => env('WORKFLOW_ACTIONS_QUEUE'),
        'connection' => env('WORKFLOW_ACTIONS_QUEUE_CONNECTION'),
        'tries'      => 3,
        'backoff'    => [10, 30, 60],
        'timeout'    => 30,
    ],

    // Lock duration
    'lock' => [
        'duration_minutes' => 30,
    ],

    // SSRF protection for webhooks
    'webhook' => [
        'allowed_schemes'      => ['https'],
        'allowed_hosts'        => [],
        'block_private_ranges' => true,
        'timeout'              => 5,
    ],
];
```

---

## Testing

```bash
composer test
# or
./vendor/bin/pest
```

---

## License

MIT. See [LICENSE](LICENSE).

---

## Credits

- [Noel Mebale](https://github.com/maestrodimateo)