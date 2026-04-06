# Using the Workflow Facade

## Prerequisites

Add the `Workflowable` trait to your Eloquent model:

```php
use Maestrodimateo\Workflow\Traits\Workflowable;

class Invoice extends Model
{
    use HasUuids, Workflowable;
}
```

When an `Invoice` is created, it's automatically placed in the DRAFT basket of **every circuit** targeting this model.

---

## Facade vs Helper

Two syntaxes are available, strictly equivalent:

```php
use Maestrodimateo\Workflow\Facades\Workflow;

// Facade
Workflow::for($invoice)->currentStatus();

// Helper
workflow($invoice)->currentStatus();

// Helper without argument (chaining)
workflow()->for($invoice)->currentStatus();
```

---

## Available Methods

### `for(Model $model): WorkflowManager`

Bind the manager to a model. Returns a **new instance** to allow concurrent use.

```php
$wf = Workflow::for($invoice);
```

---

### `in(string|Circuit $circuit): WorkflowManager`

Scope all operations to a specific circuit. Required when the model belongs to multiple circuits.

```php
Workflow::for($invoice)->in($approvalCircuit)->currentStatus();
Workflow::for($invoice)->in('circuit-uuid')->transition($basketId);
```

---

### `currentStatus(): ?Basket`

Returns the current basket (step) of the model, or `null` if not in any basket.

```php
$basket = Workflow::for($invoice)->currentStatus();

echo $basket->name;   // "Under Review"
echo $basket->status; // "REVIEW"
```

---

### `nextBaskets(): Collection`

Returns the baskets the model can transition to from its current status.

```php
$options = Workflow::for($invoice)->nextBaskets();

foreach ($options as $basket) {
    echo $basket->name; // "Approved", "Rejected"...
}
```

---

### `transition(string $nextBasketId, ?string $comment = null): bool`

Move the model from one basket to the next. Runs inside a DB transaction.

| Parameter | Type | Description |
|---|---|---|
| `$nextBasketId` | `string` | UUID of the target basket |
| `$comment` | `?string` | Optional comment (stored in history) |

```php
// Simple transition
Workflow::for($invoice)->transition($nextBasket->id);

// With comment
Workflow::for($invoice)->transition(
    $nextBasket->id,
    'Approved by the financial director'
);
```

**What happens during a transition:**

1. Lock guard — throws `ModelLockedException` if locked by another user
2. Model detached from current basket, attached to next
3. Transition actions executed (configured visually)
4. `TransitionEvent` fired → `HistoryListener` records history with duration
5. Lock released automatically
6. Your custom listeners run

---

### `history(): Collection`

Returns the full transition history, sorted newest first.
Each entry includes the **duration** spent in the previous step.

```php
$history = Workflow::for($invoice)->history();

foreach ($history as $entry) {
    echo $entry->previous_status;  // "DRAFT"
    echo $entry->next_status;      // "REVIEW"
    echo $entry->comment;          // "Sent for review"
    echo $entry->done_by;          // User ID
    echo $entry->duration_seconds; // 3600
    echo $entry->duration_human;   // "1h"
    echo $entry->created_at;       // Transition date
}
```

Human-readable formats: `45s`, `12min`, `2h 35min`, `3d 4h`.

---

### `totalDuration(): int`

Total processing time across all transitions (in seconds).

```php
$seconds = Workflow::for($invoice)->totalDuration();
```

---

### `durationInStatus(string $status): int`

Time spent in a specific status (in seconds).

```php
$seconds = Workflow::for($invoice)->durationInStatus('REVIEW');
```

---

## Multi-Circuit

### `allStatuses(): array`

Returns the current basket per circuit.

```php
$statuses = Workflow::for($invoice)->allStatuses();
// [
//     'circuit-a-id' => ['circuit' => Circuit, 'basket' => Basket],
//     'circuit-b-id' => ['circuit' => Circuit, 'basket' => Basket],
// ]
```

### `circuits(): Collection`

Lists all circuits the model belongs to.

```php
$circuits = Workflow::for($invoice)->circuits();
```

---

## Resource Locking

### `lock(?int $minutes = null): WorkflowLock`

Lock the model for exclusive access. Defaults to `workflow.lock.duration_minutes` config.

```php
Workflow::for($invoice)->lock();     // Default duration
Workflow::for($invoice)->lock(60);   // 1 hour
```

Throws `ModelLockedException` if already locked by another user. Extends the lock if called by the same user.

### `unlock(bool $force = false): void`

Release the lock. Only the lock owner can unlock unless `force: true`.

```php
Workflow::for($invoice)->unlock();
Workflow::for($invoice)->unlock(force: true); // Admin override
```

### `isLocked(): bool`

```php
Workflow::for($invoice)->isLocked(); // true/false
```

### `isLockedByMe(): bool`

```php
Workflow::for($invoice)->isLockedByMe(); // true if current user holds the lock
```

### `lockedBy(): ?string`

```php
Workflow::for($invoice)->lockedBy(); // "user-uuid" or null
```

### `lockExpiration(): ?Carbon`

```php
Workflow::for($invoice)->lockExpiration(); // Carbon instance or null
```

---

## Requirements

### `requiredDocuments(string $nextBasketId): array`

Returns documents required for a specific transition (from `require_document` actions).

```php
$docs = Workflow::for($invoice)->requiredDocuments($basketId);
// [
//     ['type' => 'id_card', 'label' => 'ID Card'],
//     ['type' => 'proof_of_address', 'label' => 'Proof of Address'],
// ]
```

### `requirements(): array`

Returns all requirements for every available next transition.

```php
$reqs = Workflow::for($invoice)->requirements();
// [
//     'basket-uuid' => [
//         'basket' => Basket,
//         'label' => 'Approve',
//         'documents' => [['type' => '...', 'label' => '...']],
//     ],
// ]
```

---

## Role-Based Queries

### `circuitsForRole(string $role): Collection`

```php
$circuits = Workflow::circuitsForRole('manager');
```

### `circuitsForRoles(array $roles): Collection`

```php
$circuits = Workflow::circuitsForRoles(['admin', 'manager']);
```

### `basketsForRole(string $role, ?string $circuitId = null): Collection`

```php
$baskets = Workflow::basketsForRole('validator');
$baskets = Workflow::basketsForRole('validator', $circuit->id);
```

### `basketsForRoles(array $roles, ?string $circuitId = null): Collection`

```php
$baskets = Workflow::basketsForRoles(['admin', 'operator'], $circuit->id);
```

### Eloquent Scopes

```php
Circuit::forRole('admin')->get();
Basket::forRoles(['admin', 'manager'])->get();

$circuit->hasRole('admin');  // true/false
$basket->hasRole('manager'); // true/false
```

---

## Transition Actions

Actions are classes executed automatically during a specific transition. They are **configured visually** in the admin UI.

### Built-in actions

| Action | Key | Description |
|---|---|---|
| Send email | `send_email` | Sends a message configured in the circuit |
| Log | `log` | Writes a log entry |
| Webhook | `webhook` | Sends an HTTP POST to a URL |
| Require documents | `require_document` | Blocks transition if documents are missing |

### Creating a custom action

```bash
php artisan make:workflow-action GeneratePdfAction
```

```php
use Maestrodimateo\Workflow\Contracts\TransitionAction;

class GeneratePdfAction implements TransitionAction
{
    public static function key(): string { return 'generate_pdf'; }
    public static function label(): string { return 'Generate PDF'; }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        PdfGenerator::generate($model, $config['template'] ?? 'default');
    }
}
```

### Registering an action

```php
// In AppServiceProvider::boot()
Workflow::registerAction(GeneratePdfAction::class);
```

The action immediately appears in the admin UI's "Add action" menu.

### Full execution order

```
1. Lock guard (ModelLockedException if locked by another user)
2. Model detached/attached (basket change)
3. Transition actions executed (from pivot JSON config)
4. TransitionEvent fired → HistoryListener records history
5. Lock auto-released
6. Your custom event listeners run
```

---

## Event Listeners

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
    $event->comment;       // The comment
}
```

---

## Workflowable Trait

The trait adds methods directly on your model:

```php
$invoice->baskets;                       // All baskets (across all circuits)
$invoice->histories;                     // All history entries
$invoice->currentStatus();               // Last basket (any circuit)
$invoice->currentStatus($circuit);       // Current basket in a specific circuit
$invoice->workflowLock;                  // Active lock (or null)

// Scopes
Invoice::fromBasket($basket)->get();     // Models in a specific basket
Invoice::unlocked()->get();              // Available models (not locked)
Invoice::lockedBy($userId)->get();       // Models locked by a specific user
```

---

## Examples

### Basic controller

```php
use Maestrodimateo\Workflow\Facades\Workflow;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice)
    {
        $wf = Workflow::for($invoice);

        return view('invoices.show', [
            'invoice'    => $invoice,
            'status'     => $wf->currentStatus(),
            'nextSteps'  => $wf->nextBaskets(),
            'history'    => $wf->history(),
            'totalTime'  => $wf->totalDuration(),
        ]);
    }

    public function transition(Request $request, Invoice $invoice)
    {
        $request->validate([
            'basket_id' => 'required|uuid',
            'comment'   => 'nullable|string|max:255',
        ]);

        Workflow::for($invoice)->transition(
            $request->basket_id,
            $request->comment,
        );

        return back()->with('success', 'Status updated');
    }
}
```

### Duration tracking

```php
class PerformanceController extends Controller
{
    public function report(Invoice $invoice)
    {
        $wf = Workflow::for($invoice);

        return [
            'total_seconds'   => $wf->totalDuration(),
            'review_duration' => $wf->durationInStatus('REVIEW'),
            'steps' => $wf->history()->map(fn ($h) => [
                'from'     => $h->previous_status,
                'to'       => $h->next_status,
                'duration' => $h->duration_human,
                'by'       => $h->done_by,
                'at'       => $h->created_at->toDateTimeString(),
            ]),
        ];
    }
}
```

### Multi-circuit

```php
class InvoiceController extends Controller
{
    public function dashboard(Invoice $invoice)
    {
        $statuses = Workflow::for($invoice)->allStatuses();

        Workflow::for($invoice)
            ->in($approvalCircuitId)
            ->transition($reviewBasketId, 'Sent for validation');
    }
}
```

### Locking

```php
use Maestrodimateo\Workflow\Exceptions\ModelLockedException;

class InvoiceController extends Controller
{
    public function take(Invoice $invoice)
    {
        try {
            Workflow::for($invoice)->lock();
            return back()->with('success', 'Model locked');
        } catch (ModelLockedException $e) {
            return back()->withErrors(['lock' => $e->getMessage()]);
        }
    }

    public function transition(Request $request, Invoice $invoice)
    {
        try {
            Workflow::for($invoice)->transition($request->basket_id);
            return back()->with('success', 'Status updated');
        } catch (ModelLockedException $e) {
            return back()->withErrors(['lock' => $e->getMessage()]);
        }
    }

    public function release(Invoice $invoice)
    {
        Workflow::for($invoice)->unlock();
        return back()->with('success', 'Model unlocked');
    }
}
```

### Custom actions

```php
// AppServiceProvider::boot()
Workflow::registerAction(GenerateInvoicePdfAction::class);
Workflow::registerAction(NotifyClientBySmsAction::class);
```
