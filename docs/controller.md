# Integrating Workflow into Your Controllers

This guide shows how to connect the package to your Laravel controllers. The package does not provide an application-level controller — that's your responsibility, because every project has its own permissions, validation, and response needs.

---

## Simple Controller

One controller per model. The most straightforward approach.

```php
use Maestrodimateo\Workflow\Facades\Workflow;
use Maestrodimateo\Workflow\Exceptions\ModelLockedException;

class InvoiceWorkflowController extends Controller
{
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

    public function lock(Invoice $invoice)
    {
        try {
            Workflow::for($invoice)->lock();
            return response()->json(['message' => 'Model locked']);
        } catch (ModelLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 423);
        }
    }

    public function unlock(Invoice $invoice)
    {
        Workflow::for($invoice)->unlock();
        return response()->json(['message' => 'Model unlocked']);
    }

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

## Displaying Requirements Before a Transition

If `require_document` actions are configured on a transition, show them to the user:

```php
class InvoiceWorkflowController extends Controller
{
    public function nextSteps(Invoice $invoice)
    {
        $wf = Workflow::for($invoice);

        $steps = $wf->nextBaskets()->map(fn ($basket) => [
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
| No dedicated controller | Workflow integrated in your existing controllers |

The package does not force any approach. Choose what fits your architecture.
