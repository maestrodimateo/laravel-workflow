# Intégrer le workflow dans vos contrôleurs

Ce guide montre comment connecter le package à vos contrôleurs Laravel. Le package ne fournit pas de contrôleur applicatif — c'est votre responsabilité, car chaque projet a ses propres besoins en permissions, validation et réponses.

---

## Contrôleur simple (un seul modèle)

```php
use Maestrodimateo\Workflow\Facades\Workflow;
use Maestrodimateo\Workflow\Exceptions\ModelLockedException;

class InvoiceWorkflowController extends Controller
{
    /**
     * Afficher le statut workflow d'une facture.
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
     * Transitionner la facture vers un nouveau panier.
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

            return back()->with('success', 'Statut mis à jour');
        } catch (ModelLockedException $e) {
            return back()->withErrors(['lock' => $e->getMessage()]);
        }
    }

    /**
     * Verrouiller la facture pour traitement exclusif.
     */
    public function lock(Invoice $invoice)
    {
        try {
            Workflow::for($invoice)->lock();
            return response()->json(['message' => 'Dossier verrouillé']);
        } catch (ModelLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 423);
        }
    }

    /**
     * Libérer le verrou.
     */
    public function unlock(Invoice $invoice)
    {
        Workflow::for($invoice)->unlock();
        return response()->json(['message' => 'Dossier libéré']);
    }

    /**
     * Historique des transitions.
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

Routes :

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

## Contrôleur multi-circuit

Si votre modèle appartient à plusieurs circuits, passez l'ID du circuit :

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

        // Vue d'ensemble de tous les circuits
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

        return back()->with('success', 'Statut mis à jour');
    }
}
```

---

## Contrôleur générique (plusieurs modèles)

Si vous voulez un seul contrôleur pour tous vos modèles workflow :

```php
class WorkflowController extends Controller
{
    /**
     * Mapping des types vers les modèles.
     * Vous pouvez aussi mettre ça dans config/workflow.php.
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

            return response()->json(['message' => 'Transitionné']);
        } catch (ModelLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 423);
        }
    }

    public function lock(string $type, string $id)
    {
        $model = $this->resolve($type, $id);

        try {
            Workflow::for($model)->lock();
            return response()->json(['message' => 'Verrouillé']);
        } catch (ModelLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 423);
        }
    }

    public function unlock(string $type, string $id)
    {
        $model = $this->resolve($type, $id);
        Workflow::for($model)->unlock();

        return response()->json(['message' => 'Déverrouillé']);
    }

    public function history(string $type, string $id)
    {
        $model = $this->resolve($type, $id);

        return response()->json(Workflow::for($model)->history());
    }

    /**
     * Résoudre le modèle depuis le type et l'ID.
     */
    private function resolve(string $type, string $id): Model
    {
        $class = $this->models[$type] ?? null;
        abort_unless($class, 404, "Type [{$type}] inconnu");

        return $class::findOrFail($id);
    }
}
```

Routes :

```php
Route::prefix('workflow/{type}/{id}')->group(function () {
    Route::get('/status', [WorkflowController::class, 'status']);
    Route::post('/transition', [WorkflowController::class, 'transition']);
    Route::post('/lock', [WorkflowController::class, 'lock']);
    Route::delete('/lock', [WorkflowController::class, 'unlock']);
    Route::get('/history', [WorkflowController::class, 'history']);
});
```

Usage :

```
GET    /workflow/invoice/uuid-123/status
POST   /workflow/leave-request/uuid-456/transition
POST   /workflow/purchase/uuid-789/lock
```

---

## Afficher les prérequis avant une transition

Si des actions `require_document` sont configurées sur une transition, affichez-les à l'utilisateur :

```php
class InvoiceWorkflowController extends Controller
{
    public function nextSteps(Invoice $invoice)
    {
        $wf = Workflow::for($invoice);
        $nextBaskets = $wf->nextBaskets();

        $steps = $nextBaskets->map(fn ($basket) => [
            'basket'       => $basket,
            'label'        => $basket->pivot->label,
            'documents'    => $wf->requiredDocuments($basket->id),
        ]);

        return response()->json($steps);
    }
}
```

Côté frontend (Blade) :

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
                    <span class="text-red-600">manquant</span>
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

## Avec Inertia / Vue / React

Le principe est le même — le contrôleur retourne les données, le frontend les affiche :

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

## Résumé

| Approche | Quand l'utiliser |
|---|---|
| Contrôleur simple | Un seul modèle avec un workflow |
| Contrôleur multi-circuit | Un modèle dans plusieurs workflows |
| Contrôleur générique | Plusieurs modèles, mêmes besoins |
| Pas de contrôleur dédié | Workflow intégré dans vos contrôleurs existants |

Le package ne force aucune approche. Choisissez celle qui correspond à votre architecture.
