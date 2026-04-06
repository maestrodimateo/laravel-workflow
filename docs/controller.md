# Integrating Workflow into Your Controllers

The package auto-discovers all models that use the `Workflowable` trait via `Circuit.targetModel`. You don't need to list them anywhere — just create a single generic controller that works with any workflowable model.

---

## Generic Workflow Controller

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Maestrodimateo\Workflow\Exceptions\ModelLockedException;
use Maestrodimateo\Workflow\Facades\Workflow;
use Maestrodimateo\Workflow\Models\Circuit;

class WorkflowController extends Controller
{
    /**
     * Get the workflow status of any model.
     *
     * GET /workflow/{type}/{id}/status
     * GET /workflow/{type}/{id}/status?circuit_id=uuid  (multi-circuit)
     */
    public function status(Request $request, string $type, string $id)
    {
        $model = $this->resolve($type, $id);
        $wf = $this->scoped($request, $model);

        return response()->json([
            'status'   => $wf->currentStatus(),
            'next'     => $wf->nextBaskets(),
            'locked'   => $wf->isLocked(),
            'lockedBy' => $wf->lockedBy(),
        ]);
    }

    /**
     * Get all statuses across circuits.
     *
     * GET /workflow/{type}/{id}/circuits
     */
    public function circuits(string $type, string $id)
    {
        $model = $this->resolve($type, $id);

        return response()->json([
            'statuses' => Workflow::for($model)->allStatuses(),
            'circuits' => Workflow::for($model)->circuits(),
        ]);
    }

    /**
     * Transition to a new basket.
     *
     * POST /workflow/{type}/{id}/transition
     * Body: { basket_id, comment?, circuit_id? }
     */
    public function transition(Request $request, string $type, string $id)
    {
        $model = $this->resolve($type, $id);

        $request->validate([
            'basket_id'  => 'required|uuid',
            'comment'    => 'nullable|string|max:255',
            'circuit_id' => 'nullable|uuid',
        ]);

        try {
            $this->scoped($request, $model)->transition(
                $request->basket_id,
                $request->comment,
            );

            return response()->json(['message' => 'Status updated']);
        } catch (ModelLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 423);
        }
    }

    /**
     * Lock the model for exclusive access.
     *
     * POST /workflow/{type}/{id}/lock
     */
    public function lock(string $type, string $id)
    {
        $model = $this->resolve($type, $id);

        try {
            $lock = Workflow::for($model)->lock();
            return response()->json(['message' => 'Locked', 'expires_at' => $lock->expires_at]);
        } catch (ModelLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 423);
        }
    }

    /**
     * Release the lock.
     *
     * DELETE /workflow/{type}/{id}/lock
     */
    public function unlock(string $type, string $id)
    {
        $model = $this->resolve($type, $id);
        Workflow::for($model)->unlock();

        return response()->json(['message' => 'Unlocked']);
    }

    /**
     * Get the transition history.
     *
     * GET /workflow/{type}/{id}/history
     */
    public function history(Request $request, string $type, string $id)
    {
        $model = $this->resolve($type, $id);

        return response()->json([
            'history'  => $this->scoped($request, $model)->history(),
            'duration' => $this->scoped($request, $model)->totalDuration(),
        ]);
    }

    /**
     * Get required documents for a transition.
     *
     * GET /workflow/{type}/{id}/requirements/{basketId}
     */
    public function requirements(Request $request, string $type, string $id, string $basketId)
    {
        $model = $this->resolve($type, $id);

        return response()->json(
            $this->scoped($request, $model)->requiredDocuments($basketId)
        );
    }

    /**
     * Resolve a model from its short type name and ID.
     *
     * The type is matched against Circuit.targetModel — no manual mapping needed.
     * "invoice" matches App\Models\Invoice, "leave-request" matches App\Models\LeaveRequest, etc.
     */
    private function resolve(string $type, string $id): Model
    {
        $className = $this->resolveClassName($type);

        abort_unless($className, 404, "No workflow model found for type [{$type}]");

        return $className::findOrFail($id);
    }

    /**
     * Find the model class from a short type name by looking at existing circuits.
     *
     * "invoice"       → App\Models\Invoice       (if a circuit targets it)
     * "leave-request" → App\Models\LeaveRequest   (if a circuit targets it)
     */
    private function resolveClassName(string $type): ?string
    {
        $normalized = str($type)->studly()->toString();

        return Circuit::query()
            ->pluck('targetModel')
            ->first(fn (string $class) => class_basename($class) === $normalized);
    }

    /**
     * Apply circuit scoping if circuit_id is provided.
     */
    private function scoped(Request $request, Model $model): \Maestrodimateo\Workflow\WorkflowManager
    {
        $wf = Workflow::for($model);

        if ($request->filled('circuit_id')) {
            $wf = $wf->in($request->circuit_id);
        }

        return $wf;
    }
}
```

---

## Routes

```php
Route::prefix('workflow/{type}/{id}')->group(function () {
    Route::get('/status', [WorkflowController::class, 'status']);
    Route::get('/circuits', [WorkflowController::class, 'circuits']);
    Route::post('/transition', [WorkflowController::class, 'transition']);
    Route::post('/lock', [WorkflowController::class, 'lock']);
    Route::delete('/lock', [WorkflowController::class, 'unlock']);
    Route::get('/history', [WorkflowController::class, 'history']);
    Route::get('/requirements/{basketId}', [WorkflowController::class, 'requirements']);
});
```

---

## Usage

```
GET    /workflow/invoice/uuid-123/status
GET    /workflow/invoice/uuid-123/status?circuit_id=uuid-circuit
GET    /workflow/invoice/uuid-123/circuits
POST   /workflow/invoice/uuid-123/transition       { basket_id, comment?, circuit_id? }
POST   /workflow/leave-request/uuid-456/lock
DELETE /workflow/leave-request/uuid-456/lock
GET    /workflow/purchase-order/uuid-789/history
GET    /workflow/invoice/uuid-123/requirements/uuid-basket
```

The `{type}` parameter is automatically resolved to the model class by matching against `Circuit.targetModel`. For example, if a circuit targets `App\Models\Invoice`, the type `invoice` resolves to that class. No configuration needed — if the model uses `Workflowable` and has a circuit, it works.

---

## Adding Permissions

The controller above has no authorization. Add your own middleware or policy checks:

```php
Route::prefix('workflow/{type}/{id}')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // ...
    });
```

Or inside the controller:

```php
public function transition(Request $request, string $type, string $id)
{
    $model = $this->resolve($type, $id);

    // Your authorization logic
    $this->authorize('transition', $model);

    // ...
}
```

---

## With Inertia / Vue / React

The same controller works — just change the response format:

```php
public function status(Request $request, string $type, string $id)
{
    $model = $this->resolve($type, $id);
    $wf = $this->scoped($request, $model);

    return Inertia::render('Workflow/Status', [
        'model'        => $model,
        'type'         => $type,
        'status'       => $wf->currentStatus(),
        'nextSteps'    => $wf->nextBaskets(),
        'history'      => $wf->history(),
        'isLocked'     => $wf->isLocked(),
        'requirements' => $wf->requirements(),
    ]);
}
```
