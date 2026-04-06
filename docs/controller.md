# Integrating Workflow into Your Controllers

This guide shows how to connect the package to your Laravel controllers. The package does not provide an application-level controller — that's your responsibility, because every project has its own permissions, validation, and response needs.

---

## Simple Controller (single model)

```php
use Maestrodimateo\Workflow\Facades\Workflow;
use Maestrodimateo\Workflow\Exceptions\ModelLockedException;

class InvoiceWorkflowController extends Controller
{
    /**
     * Show the workflow status of an invoice.
     */
    public function status(Invoice $invoice)
    {
        $wf = Workflow::for($invoice);

        return response()->json([
            'current_status' => $wf->currentStatus(),
            'next_steps'     => $wf->nextBaskets(),
            'is_locked'      => $wf->isLocked(),
            'locked_by'      => $wf->lockedBy(),
        ]);
    }

    /**
     * Transition the invoice to a new basket.
     */
    public function transition(Request $request, Invoice $invoice)
    {
        $request->validate([
            'basket_id' => 'required|uuid',
            'comment'   => 'nullable|string|max:255',
        ]);

        try {
            Workflow::for($invoice)->transition(
                $request->basket_id,
                $request->comment,
            );

            return back()->with('success', 'Status updated');
        } catch (ModelLockedException $e) {
            return back()->withErrors(['lock' => $e->getMessage()]);
        }
    }

    /**
     * Lock the invoice for exclusive processing.
     */
    public function lock(Invoice $invoice)
    {
        try {
            Workflow::for($invoice)->lock();
            return response()->json(['message' => 'Model locked']);
        } catch (ModelLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 423);
        }
    }

    /**
     * Release the lock.
     */
    public function unlock(Invoice $invoice)
    {
        Workflow::for($invoice)->unlock();
        return response()->json(['message' => 'Model unlocked']);
    }

    /**
     * Transition history.
     */
    public function history(Invoice $invoice)
    {
        return response()->json([
            'history'        => Workflow::for($invoice)->history(),
            'total_duration' => Workflow::for($invoice)->totalDuration(),
        ]);
    }
}
```

Routes:

```php
Route::prefix('invoices/{invoice}/workflow')->group(function () {
    Route::get('/status', [InvoiceWorkflowController::class, 'status']);
    Route::post('/transition', [InvoiceWorkflowController::class, 'transition']);
    Route::post('/lock', [InvoiceWorkflowController::class, 'lock']);
    Route::delete('/lock', [InvoiceWorkflowController::class, 'unlock']);
    Route::get('/history', [InvoiceWorkflowController::class, 'history']);
});
```

---

## Multi-Circuit Controller

If your model belongs to multiple circuits, pass the circuit ID:

```php
class InvoiceWorkflowController extends Controller
{
    public function status(Request $request, Invoice $invoice)
    {
        $circuitId = $request->query('circuit_id');

        if ($circuitId) {
            $wf = Workflow::for($invoice)->in($circuitId);

            return response()->json([
                'circuit'  => $circuitId,
                'status'   => $wf->currentStatus(),
                'next'     => $wf->nextBaskets(),
            ]);
        }

        // Overview across all circuits
        return response()->json([
            'statuses' => Workflow::for($invoice)->allStatuses(),
            'circuits' => Workflow::for($invoice)->circuits(),
        ]);
    }

    public function transition(Request $request, Invoice $invoice)
    {
        $request->validate([
            'basket_id'  => 'required|uuid',
            'circuit_id' => 'required|uuid',
            'comment'    => 'nullable|string|max:255',
        ]);

        Workflow::for($invoice)
            ->in($request->circuit_id)
            ->transition($request->basket_id, $request->comment);

        return back()->with('success', 'Status updated');
    }
}
```

---

## Generic Controller (multiple models)

If you want a single controller for all your workflow models:

```php
class WorkflowController extends Controller
{
    /**
     * Model type mapping.
     * You can also put this in config/workflow.php.
     */
    private array $models = [
        'invoice'       => \App\Models\Invoice::class,
        'leave-request' => \App\Models\LeaveRequest::class,
        'purchase'      => \App\Models\PurchaseOrder::class,
    ];

    public function status(string $type, string $id)
    {
        $model = $this->resolve($type, $id);
        $wf = Workflow::for($model);

        return response()->json([
            'type'    => $type,
            'status'  => $wf->currentStatus(),
            'next'    => $wf->nextBaskets(),
            'locked'  => $wf->isLocked(),
        ]);
    }

    public function transition(Request $request, string $type, string $id)
    {
        $model = $this->resolve($type, $id);

        $request->validate([
            'basket_id' => 'required|uuid',
            'comment'   => 'nullable|string|max:255',
        ]);

        try {
            Workflow::for($model)->transition(
                $request->basket_id,
                $request->comment,
            );

            return response()->json(['message' => 'Transitioned']);
        } catch (ModelLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 423);
        }
    }

    public function lock(string $type, string $id)
    {
        $model = $this->resolve($type, $id);

        try {
            Workflow::for($model)->lock();
            return response()->json(['message' => 'Locked']);
        } catch (ModelLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 423);
        }
    }

    public function unlock(string $type, string $id)
    {
        $model = $this->resolve($type, $id);
        Workflow::for($model)->unlock();

        return response()->json(['message' => 'Unlocked']);
    }

    public function history(string $type, string $id)
    {
        $model = $this->resolve($type, $id);

        return response()->json(Workflow::for($model)->history());
    }

    /**
     * Resolve a model from its type and ID.
     */
    private function resolve(string $type, string $id): Model
    {
        $class = $this->models[$type] ?? null;
        abort_unless($class, 404, "Unknown type [{$type}]");

        return $class::findOrFail($id);
    }
}
```

Routes:

```php
Route::prefix('workflow/{type}/{id}')->group(function () {
    Route::get('/status', [WorkflowController::class, 'status']);
    Route::post('/transition', [WorkflowController::class, 'transition']);
    Route::post('/lock', [WorkflowController::class, 'lock']);
    Route::delete('/lock', [WorkflowController::class, 'unlock']);
    Route::get('/history', [WorkflowController::class, 'history']);
});
```

Usage:

```
GET    /workflow/invoice/uuid-123/status
POST   /workflow/leave-request/uuid-456/transition
POST   /workflow/purchase/uuid-789/lock
```

---

## Displaying Requirements Before a Transition

If `require_document` actions are configured on a transition, show them to the user:

```php
class InvoiceWorkflowController extends Controller
{
    public function nextSteps(Invoice $invoice)
    {
        $wf = Workflow::for($invoice);
        $nextBaskets = $wf->nextBaskets();

        $steps = $nextBaskets->map(fn ($basket) => [
            'basket'    => $basket,
            'label'     => $basket->pivot->label,
            'documents' => $wf->requiredDocuments($basket->id),
        ]);

        return response()->json($steps);
    }
}
```

In a Blade view:

```blade
@foreach ($steps as $step)
    <div class="border rounded p-4">
        <h3>{{ $step['basket']->name }}</h3>

        @foreach ($step['documents'] as $doc)
            <p>
                {{ $doc['label'] }}
                @if ($invoice->documents()->where('type', $doc['type'])->exists())
                    <span class="text-green-600">✓</span>
                @else
                    <span class="text-red-600">missing</span>
                @endif
            </p>
        @endforeach

        <form method="POST" action="/invoices/{{ $invoice->id }}/workflow/transition">
            @csrf
            <input type="hidden" name="basket_id" value="{{ $step['basket']->id }}">
            <button type="submit">{{ $step['label'] ?? $step['basket']->name }}</button>
        </form>
    </div>
@endforeach
```

---

## With Inertia / Vue / React

Same principle — the controller returns data, the frontend renders it:

```php
class InvoiceController extends Controller
{
    public function show(Invoice $invoice)
    {
        $wf = Workflow::for($invoice);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
            'workflow' => [
                'status'       => $wf->currentStatus(),
                'nextSteps'    => $wf->nextBaskets(),
                'history'      => $wf->history(),
                'isLocked'     => $wf->isLocked(),
                'lockedBy'     => $wf->lockedBy(),
                'requirements' => $wf->requirements(),
            ],
        ]);
    }
}
```

---

## Summary

| Approach | When to use |
|---|---|
| Simple controller | One model with one workflow |
| Multi-circuit controller | One model in multiple workflows |
| Generic controller | Multiple models, same needs |
| No dedicated controller | Workflow integrated in your existing controllers |

The package does not force any approach. Choose what fits your architecture.
