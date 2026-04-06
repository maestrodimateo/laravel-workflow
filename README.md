# Laravel Workflow

[![Latest Version on Packagist](https://img.shields.io/packagist/v/maestrodimateo/laravel-workflow.svg)](https://packagist.org/packages/maestrodimateo/laravel-workflow)
[![License](https://img.shields.io/packagist/l/maestrodimateo/laravel-workflow.svg)](https://packagist.org/packages/maestrodimateo/laravel-workflow)

A visual, configurable workflow engine for Laravel. Define circuits (workflow definitions), baskets (steps), and transitions — then move any Eloquent model through them with a clean Facade API.

Comes with a **built-in visual admin interface** to design your workflows by drag-and-drop.

---

## Features

- **Visual workflow designer** — drag-and-drop baskets, draw transitions, configure actions
- **Facade & helper** — `Workflow::for($model)->transition($basketId)` or `workflow($model)->transition($basketId)`
- **Multi-circuit** — a model can belong to multiple workflows, scoped with `in($circuit)`
- **Resource locking** — prevent concurrent access with `lock()` / `unlock()`
- **Role-based access** — define allowed roles per circuit and per basket
- **Transition actions** — attach actions (email, webhook, log, require documents, custom) to transitions
- **Duration tracking** — automatic timing between steps with human-readable formatting
- **Full history** — every transition is logged with who, when, how long, and why
- **Message templates** — WYSIWYG editor with variable interpolation
- **Export / Import** — share workflows as JSON files + PNG image export
- **Dark mode** — the admin UI supports light and dark themes
- **Zero build step** — the admin UI uses Alpine.js + Tailwind CDN, no npm required

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

## Quick Start

### 1. Add the trait to your model

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Maestrodimateo\Workflow\Traits\Workflowable;

class Invoice extends Model
{
    use HasUuids, Workflowable;
}
```

When an `Invoice` is created, it's automatically placed in the **DRAFT** basket of the circuit targeting it.

### 2. Open the visual designer

Navigate to `/workflow/admin` in your browser. From there you can:

- Create a **circuit** (workflow) targeting your model
- Add **baskets** (steps) with colors and roles
- Draw **transitions** (links) between baskets by dragging from one to another
- Configure **actions** on each transition (send email, call webhook, etc.)

### 3. Transition models in your code

```php
use Maestrodimateo\Workflow\Facades\Workflow;

// Get current status
$basket = Workflow::for($invoice)->currentStatus();
echo $basket->name;   // "Brouillon"
echo $basket->status; // "DRAFT"

// See available next steps
$options = Workflow::for($invoice)->nextBaskets();

// Transition
Workflow::for($invoice)->transition(
    $nextBasket->id,
    'Approved by manager',  // optional comment
);
```

The `workflow()` helper is also available:

```php
workflow($invoice)->currentStatus();
workflow($invoice)->transition($basketId);
```

---

## Facade API Reference

All methods are available via `Workflow::` or `workflow()->`.

### Model-bound methods

These methods require `Workflow::for($model)` first:

```php
$wf = Workflow::for($model);
```

| Method | Returns | Description |
|---|---|---|
| `in($circuit)` | `WorkflowManager` | Scope to a specific circuit (required for multi-circuit) |
| `currentStatus()` | `?Basket` | Current basket (step) of the model |
| `nextBaskets()` | `Collection` | Available baskets to transition to |
| `transition($id, $comment)` | `bool` | Move the model to the next basket |
| `history()` | `Collection` | Full transition history with durations |
| `totalDuration()` | `int` | Total processing time in seconds |
| `durationInStatus($status)` | `int` | Time spent in a specific status (seconds) |
| `allStatuses()` | `array` | Current basket per circuit |
| `circuits()` | `Collection` | All circuits the model belongs to |
| `lock($minutes)` | `WorkflowLock` | Lock the model for exclusive access |
| `unlock($force)` | `void` | Release the lock |
| `isLocked()` | `bool` | Check if locked |
| `isLockedByMe()` | `bool` | Check if locked by the current user |
| `lockedBy()` | `?string` | User ID holding the lock |
| `lockExpiration()` | `?Carbon` | When the lock expires |
| `requiredDocuments($basketId)` | `array` | Documents required for a transition |
| `requirements()` | `array` | All requirements for all next transitions |

### Role-based queries

These methods don't require `for()`:

```php
// Circuits accessible to a role
Workflow::circuitsForRole('manager');
Workflow::circuitsForRoles(['admin', 'manager']);

// Baskets accessible to a role (optionally scoped to a circuit)
Workflow::basketsForRole('validator');
Workflow::basketsForRole('validator', $circuitId);
Workflow::basketsForRoles(['admin', 'operator'], $circuitId);
```

Eloquent scopes are also available directly:

```php
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Models\Basket;

Circuit::forRole('admin')->get();
Basket::forRoles(['admin', 'manager'])->get();

$basket->hasRole('validator'); // true/false
$circuit->hasRole('admin');    // true/false
```

---

## Duration Tracking

Every transition automatically records the time spent in the previous step:

```php
$history = Workflow::for($invoice)->history();

foreach ($history as $entry) {
    echo $entry->previous_status;  // "DRAFT"
    echo $entry->next_status;      // "REVIEW"
    echo $entry->duration_seconds; // 3600
    echo $entry->duration_human;   // "1h"
    echo $entry->done_by;          // User ID
    echo $entry->comment;          // "Sent for review"
}

// Total time
$seconds = Workflow::for($invoice)->totalDuration();

// Time in a specific step
$reviewTime = Workflow::for($invoice)->durationInStatus('REVIEW');
```

Human-readable formats: `45s`, `12min`, `2h 35min`, `3j 4h`.

---

## Multi-Circuit Support

A model can belong to multiple circuits simultaneously. Use `in()` to scope operations:

```php
// Scope to a specific circuit
Workflow::for($invoice)->in($approvalCircuit)->currentStatus();
Workflow::for($invoice)->in($complianceCircuit)->transition($basketId);
Workflow::for($invoice)->in('circuit-uuid')->history();

// See status in ALL circuits at once
$statuses = Workflow::for($invoice)->allStatuses();
// [
//     'circuit-a-id' => ['circuit' => Circuit, 'basket' => Basket],
//     'circuit-b-id' => ['circuit' => Circuit, 'basket' => Basket],
// ]

// List circuits the model belongs to
$circuits = Workflow::for($invoice)->circuits();
```

When a model is created, it's automatically attached to the DRAFT basket of **every** circuit targeting its class.

---

## Resource Locking

Prevent multiple operators from working on the same model simultaneously:

```php
// Lock the model (default: 30 minutes)
Workflow::for($invoice)->lock();
Workflow::for($invoice)->lock(60); // 1 hour

// Check lock status
Workflow::for($invoice)->isLocked();       // true
Workflow::for($invoice)->isLockedByMe();   // true
Workflow::for($invoice)->lockedBy();       // "user-uuid"
Workflow::for($invoice)->lockExpiration(); // Carbon instance

// Transition (auto-checks the lock)
Workflow::for($invoice)->transition($basketId);
// → OK if you hold the lock (lock is released after transition)
// → ModelLockedException if locked by someone else

// Release manually
Workflow::for($invoice)->unlock();

// Admin force unlock
Workflow::for($invoice)->unlock(force: true);
```

### Query scopes

```php
// Available models (not locked or lock expired)
Invoice::fromBasket($reviewBasket)->unlocked()->get();

// Models I'm working on
Invoice::lockedBy(auth()->id())->get();
```

### Handling lock exceptions

```php
use Maestrodimateo\Workflow\Exceptions\ModelLockedException;

try {
    Workflow::for($invoice)->transition($basketId);
} catch (ModelLockedException $e) {
    return back()->withErrors([
        'lock' => $e->getMessage(),
        // "Ce dossier est verrouillé par [user] jusqu'à [14:30]."
    ]);
}
```

Configure the default lock duration in `.env`:

```env
WORKFLOW_LOCK_DURATION=30  # minutes
```

---

## Transition Actions

Actions are executed automatically when a specific transition occurs. They are **configured visually** in the admin UI.

### Built-in actions

| Action | Key | Config |
|---|---|---|
| Send email | `send_email` | Select a message from the circuit |
| Log | `log` | Optional message |
| Webhook | `webhook` | URL to POST to |
| Require documents | `require_document` | List of documents (type + label) |

### Custom actions

Generate a new action with artisan:

```bash
php artisan make:workflow-action GeneratePdfAction
```

This creates `app/Workflow/Actions/GeneratePdfAction.php`:

```php
use Maestrodimateo\Workflow\Contracts\TransitionAction;

class GeneratePdfAction implements TransitionAction
{
    public static function key(): string { return 'generate_pdf'; }
    public static function label(): string { return 'Generate Pdf'; }

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

The action immediately appears in the admin UI's "Add action" menu on any transition.

---

## Events

A `TransitionEvent` is fired after every transition. Add your own listeners:

```php
// In your EventServiceProvider
protected $listen = [
    \Maestrodimateo\Workflow\Events\TransitionEvent::class => [
        \App\Listeners\NotifySlack::class,
        \App\Listeners\SyncWithExternalSystem::class,
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
1. Lock guard — throws ModelLockedException if locked by another user
2. Model detached from current basket, attached to next
3. Transition actions executed (configured visually on the link)
4. TransitionEvent fired → HistoryListener records history with duration
5. Lock released automatically
6. Your custom listeners run
```

---

## Message Templates

Messages are created at the circuit level and can be used in transition actions (e.g., `send_email`).

The WYSIWYG editor supports **variable interpolation**:

```
Bonjour, la demande {{ reference }} a été transférée de {{ from_name }}
vers {{ to_name }} par {{ user }} le {{ datetime }}.
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
    'montant'   => fn ($model) => number_format($model->amount, 2, ',', ' ') . ' €',
],
```

---

## Export / Import

### In the admin UI

- **Export JSON** — download the full circuit definition as a `.json` file
- **Export PNG** — download a high-resolution image of the workflow diagram
- **Import** — select a `.json` file to recreate a circuit with all its configuration

### Via API

```bash
GET  /workflow/admin/api/circuits/{circuit}/export
POST /workflow/admin/api/circuits/import  # multipart form, field: "file"
```

---

## Configuration

```php
// config/workflow.php
return [
    'routes' => [
        'prefix'           => 'workflow',
        'middleware'        => ['api'],
        'admin_middleware'  => ['web'],
    ],
    'auth_identifier' => 'id',
    'message_variables' => [],
    'lock' => [
        'duration_minutes' => 30,
    ],
];
```

---

## Workflowable Trait

The trait adds these methods directly on your model:

```php
$invoice->baskets;                       // All baskets across all circuits
$invoice->histories;                     // All history entries
$invoice->currentStatus();               // Last basket (any circuit)
$invoice->currentStatus($circuit);       // Current basket in a specific circuit
$invoice->workflowLock;                  // Active lock (or null)

// Scopes
Invoice::fromBasket($basket)->get();     // Models in a specific basket
Invoice::unlocked()->get();              // Models not locked (or lock expired)
Invoice::lockedBy($userId)->get();       // Models locked by a specific user
```

---

## Admin Interface

The visual designer at `/workflow/admin` provides:

- **Circuit management** — create, edit, delete circuits with role assignment
- **Drag-and-drop canvas** — position baskets freely, auto-layout button
- **Visual linking** — click output port, then click target basket to create transitions
- **Transition config** — click a link to add label and actions
- **Message editor** — WYSIWYG editor with variable interpolation
- **Export / Import** — JSON + PNG export, JSON import
- **Zoom** — scroll wheel + controls
- **Dark mode** — toggle between light and dark themes
- **No build step** — works out of the box, powered by Alpine.js + Tailwind CDN

---

## Testing

```bash
composer test
```

Or with Pest directly:

```bash
./vendor/bin/pest
```

---

## License

MIT. See [LICENSE](LICENSE) for details.

---

## Credits

- [Noel Mebale](https://github.com/maestrodimateo)
