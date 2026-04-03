# Utilisation de la Facade Workflow

## Prérequis

Ajoutez le trait `Workflowable` sur votre modèle Eloquent :

```php
use Maestrodimateo\Workflow\Traits\Workflowable;

class Invoice extends Model
{
    use HasUuids, Workflowable;
}
```

Dès qu'une instance de `Invoice` est créée, elle est automatiquement placée dans le premier panier (DRAFT) du circuit qui la cible.

---

## Facade vs Helper

Deux syntaxes sont disponibles, strictement équivalentes :

```php
use Maestrodimateo\Workflow\Facades\Workflow;

// Facade
Workflow::for($invoice)->currentStatus();

// Helper
workflow($invoice)->currentStatus();

// Helper sans argument (chaînage)
workflow()->for($invoice)->currentStatus();
```

---

## Méthodes disponibles

### `for(Model $model): WorkflowManager`

Lie le manager à un modèle. Retourne une **nouvelle instance** pour permettre l'usage concurrent.

```php
$wf = Workflow::for($invoice);
```

---

### `currentStatus(): ?Basket`

Retourne le panier (étape) actuel du modèle, ou `null` s'il n'est dans aucun panier.

```php
$basket = Workflow::for($invoice)->currentStatus();

echo $basket->name;    // "En révision"
echo $basket->status;  // "REVIEW"
echo $basket->color;   // AllowedBasketColors::BLUE
```

---

### `nextBaskets(): Collection`

Retourne la collection des paniers vers lesquels le modèle peut transitionner depuis son statut actuel.

```php
$options = Workflow::for($invoice)->nextBaskets();

foreach ($options as $basket) {
    echo $basket->name; // "Validé", "Rejeté"...
}
```

---

### `transition(string $nextBasketId, ?string $comment = null): bool`

Fait passer le modèle d'un panier au suivant. Exécuté dans une transaction.

| Paramètre | Type | Description |
|---|---|---|
| `$nextBasketId` | `string` | UUID du panier cible |
| `$comment` | `?string` | Commentaire optionnel (stocké dans l'historique) |

```php
// Transition simple
Workflow::for($invoice)->transition($nextBasket->id);

// Avec commentaire
Workflow::for($invoice)->transition(
    $nextBasket->id,
    'Validé par le directeur financier'
);
```

**Ce qui se passe lors d'une transition :**

1. Le modèle est détaché de l'ancien panier et attaché au nouveau
2. Les **actions configurées visuellement** sur la transition sont exécutées
3. Le `TransitionEvent` est émis → `HistoryListener` enregistre l'historique avec la durée
4. Vos listeners custom s'exécutent

---

### `history(): Collection`

Retourne l'historique complet des transitions du modèle, trié du plus récent au plus ancien.
Chaque entrée contient la **durée** passée dans l'étape précédente.

```php
$history = Workflow::for($invoice)->history();

foreach ($history as $entry) {
    echo $entry->previous_status;  // "DRAFT"
    echo $entry->next_status;      // "REVIEW"
    echo $entry->comment;          // "Envoyé pour validation"
    echo $entry->done_by;          // ID de l'utilisateur
    echo $entry->duration_seconds; // 3600 (1 heure en secondes)
    echo $entry->duration_human;   // "1h"
    echo $entry->created_at;       // Date de la transition
}
```

Formats lisibles : `45s`, `12min`, `2h 35min`, `3j 4h`.

---

### `totalDuration(): int`

Retourne le temps total de traitement (somme de toutes les transitions, en secondes).

```php
$seconds = Workflow::for($invoice)->totalDuration();
```

---

### `durationInStatus(string $status): int`

Retourne le temps passé dans un statut spécifique (en secondes).

```php
$seconds = Workflow::for($invoice)->durationInStatus('REVIEW');
```

---

## Filtrer par rôle

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

### Scopes Eloquent

```php
Circuit::forRole('admin')->get();
Basket::forRoles(['admin', 'manager'])->get();

$circuit->hasRole('admin');  // true/false
$basket->hasRole('manager'); // true/false
```

---

## Actions de transition

Les actions sont des classes exécutées automatiquement lors d'une transition spécifique. Elles sont **configurées visuellement** dans l'interface admin.

### Actions intégrées

| Action | Clé | Description |
|---|---|---|
| Envoyer un email | `send_email` | Envoie un message configuré dans le circuit |
| Enregistrer dans les logs | `log` | Écrit une entrée dans les logs Laravel |
| Appeler un webhook | `webhook` | Envoie un POST HTTP vers une URL |

### Créer une action personnalisée

```bash
php artisan make:workflow-action GeneratePdfAction
```

```php
use Maestrodimateo\Workflow\Contracts\TransitionAction;

class GeneratePdfAction implements TransitionAction
{
    public static function key(): string { return 'generate_pdf'; }
    public static function label(): string { return 'Generate Pdf'; }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        PdfGenerator::generate($model, $config['template'] ?? 'default');
    }
}
```

### Enregistrer une action

```php
// Dans AppServiceProvider::boot()
Workflow::registerAction(GeneratePdfAction::class);
```

L'action apparaît automatiquement dans le menu "Ajouter" de l'interface admin.

### Ordre d'exécution complet

```
1. Déplacement du modèle (detach/attach)
2. Actions configurées visuellement (JSON du pivot transition)
3. TransitionEvent émis → HistoryListener enregistre l'historique
4. Vos listeners custom s'exécutent
```

---

## Event listeners

L'événement `TransitionEvent` est émis après chaque transition :

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
    $event->currentBasket;  // Panier de départ
    $event->nextBasket;     // Panier d'arrivée
    $event->model;          // Le modèle transitionné
    $event->comment;        // Le commentaire
}
```

---

## Méthodes du trait Workflowable

Le trait ajoute des méthodes directement sur le modèle :

```php
$invoice->baskets;          // Tous les paniers (historique des statuts)
$invoice->histories;        // Toutes les entrées d'historique
$invoice->currentStatus();  // Panier actuel

// Scope : modèles dans un panier donné
Invoice::fromBasket($basket)->get();
```

---

## Exemple complet

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

        return back()->with('success', 'Statut mis à jour');
    }
}
```

### Exemple avec suivi des durées

```php
class PerformanceController extends Controller
{
    public function report(Invoice $invoice)
    {
        $wf = Workflow::for($invoice);

        return [
            'total_seconds'  => $wf->totalDuration(),
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

### Exemple avec action custom

```php
// AppServiceProvider::boot()
Workflow::registerAction(GenerateInvoicePdfAction::class);
Workflow::registerAction(NotifyClientBySmsAction::class);
```
