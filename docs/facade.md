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

Utile pour afficher un menu de choix à l'utilisateur.

---

### `transition(string $nextBasketId, ?string $comment = null, array $users = []): bool`

Fait passer le modèle d'un panier au suivant. Exécuté dans une transaction.

| Paramètre | Type | Description |
|---|---|---|
| `$nextBasketId` | `string` | UUID du panier cible |
| `$comment` | `?string` | Commentaire optionnel (stocké dans l'historique) |
| `$users` | `array` | UUIDs des utilisateurs à assigner (optionnel) |

```php
// Transition simple
Workflow::for($invoice)->transition($nextBasket->id);

// Avec commentaire
Workflow::for($invoice)->transition(
    $nextBasket->id,
    'Validé par le directeur financier'
);

// Avec assignation d'utilisateurs
Workflow::for($invoice)->transition(
    $nextBasket->id,
    'Transmis pour traitement',
    [$userId1, $userId2]
);
```

**Ce qui se passe lors d'une transition :**

1. Les hooks `beforeTransition` sont exécutés (peuvent annuler via exception)
2. Le modèle est détaché de l'ancien panier et attaché au nouveau
3. Les utilisateurs sont assignés (si fournis)
4. Les **actions configurées visuellement** sur la transition sont exécutées
5. Le `TransitionEvent` est émis (listeners `HistoryListener`, `SendTransitionMessageListener`)
6. Les hooks `afterTransition` sont exécutés

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

La durée est calculée automatiquement entre la dernière transition (ou la création du modèle) et la transition courante.

Formats lisibles : `45s`, `12min`, `2h 35min`, `3j 4h`.

---

### `totalDuration(): int`

Retourne le temps total de traitement (somme de toutes les transitions, en secondes).

```php
$seconds = Workflow::for($invoice)->totalDuration();
echo $seconds; // 7200 → 2 heures au total
```

---

### `durationInStatus(string $status): int`

Retourne le temps passé dans un statut spécifique (en secondes).

```php
// Combien de temps la facture est restée en révision ?
$seconds = Workflow::for($invoice)->durationInStatus('REVIEW');
echo $seconds; // 5400 → 1h30
```

Utile pour les KPIs et tableaux de bord : identifier les goulots d'étranglement.

---

### `assignedUsers(): Collection`

Retourne les utilisateurs assignés au modèle dans son panier actuel.

```php
$users = Workflow::for($invoice)->assignedUsers();

foreach ($users as $user) {
    echo $user->name;
    echo $user->pivot->basket_id;
}
```

---

## Filtrer par rôle

### `circuitsForRole(string $role): Collection`

Retourne tous les circuits accessibles pour un rôle donné.

```php
$circuits = Workflow::circuitsForRole('manager');
```

### `circuitsForRoles(array $roles): Collection`

Retourne les circuits accessibles pour au moins un des rôles donnés.

```php
$circuits = Workflow::circuitsForRoles(['admin', 'manager']);
```

### `basketsForRole(string $role, ?string $circuitId = null): Collection`

Retourne les paniers accessibles pour un rôle, optionnellement filtrés par circuit.

```php
// Tous les paniers pour le rôle "validator"
$baskets = Workflow::basketsForRole('validator');

// Paniers d'un circuit spécifique
$baskets = Workflow::basketsForRole('validator', $circuit->id);
```

### `basketsForRoles(array $roles, ?string $circuitId = null): Collection`

```php
$baskets = Workflow::basketsForRoles(['admin', 'operator'], $circuit->id);
```

### Scopes Eloquent

Les mêmes filtres sont disponibles directement sur les modèles :

```php
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Models\Basket;

Circuit::forRole('admin')->get();
Circuit::forRoles(['admin', 'operator'])->with('baskets')->get();

Basket::forRole('manager')->where('circuit_id', $id)->get();
Basket::forRoles(['admin', 'manager'])->get();
```

### Vérifier sur une instance

```php
$circuit->hasRole('admin');  // true/false
$basket->hasRole('manager'); // true/false
```

---

## Hooks de transition

Enregistrez des callbacks `before` et `after` pour intervenir dans le cycle de vie des transitions.

### `beforeTransition(Closure $callback): void`

Exécuté **avant** la transaction DB. Lancer une exception annule la transition.

```php
// Dans AppServiceProvider::boot()
Workflow::beforeTransition(function ($model, $from, $to) {
    if ($to->status === 'PUBLISHED' && !$model->isComplete()) {
        throw new \Exception('Le dossier est incomplet.');
    }

    Log::info("Transition en cours : {$from->status} → {$to->status}");
});
```

| Paramètre | Type | Description |
|---|---|---|
| `$model` | `Model` | Le modèle qui transite |
| `$from` | `Basket` | Le panier de départ |
| `$to` | `Basket` | Le panier d'arrivée |

### `afterTransition(Closure $callback): void`

Exécuté **après** dans la transaction DB (avant le commit).

```php
Workflow::afterTransition(function ($model, $from, $to, $comment) {
    Notification::send($model->owner, new StatusChangedNotification($from, $to));

    if ($to->status === 'DONE') {
        $model->update(['completed_at' => now()]);
    }
});
```

| Paramètre | Type | Description |
|---|---|---|
| `$model` | `Model` | Le modèle qui transite |
| `$from` | `Basket` | Le panier de départ |
| `$to` | `Basket` | Le panier d'arrivée |
| `$comment` | `?string` | Le commentaire de transition |

### `clearHooks(): void`

Supprime tous les hooks enregistrés (utile dans les tests).

```php
Workflow::clearHooks();
```

---

## Actions de transition

Les actions sont des classes exécutées automatiquement lors d'une transition spécifique. Elles sont **configurées visuellement** dans l'interface admin sur chaque lien entre deux paniers.

### Actions intégrées

| Action | Clé | Description |
|---|---|---|
| Envoyer un email | `send_email` | Envoie un message configuré dans le circuit |
| Enregistrer dans les logs | `log` | Écrit une entrée dans les logs Laravel |
| Appeler un webhook | `webhook` | Envoie un POST HTTP vers une URL |

### Créer une action personnalisée

```php
use Maestrodimateo\Workflow\Contracts\TransitionAction;

class GeneratePdfAction implements TransitionAction
{
    public static function key(): string
    {
        return 'generate_pdf';
    }

    public static function label(): string
    {
        return 'Générer un PDF';
    }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        $template = $config['template'] ?? 'default';
        PdfGenerator::generate($model, $template);
    }
}
```

### Enregistrer une action

```php
// Dans AppServiceProvider::boot()
Workflow::registerAction(GeneratePdfAction::class);
```

L'action apparaît automatiquement dans le menu "Ajouter" de l'interface admin, prête à être configurée visuellement sur n'importe quelle transition.

### Lister les actions enregistrées

```php
$actions = Workflow::getRegisteredActions();
// ['send_email' => SendEmailAction::class, 'log' => LogTransitionAction::class, ...]
```

### Ordre d'exécution complet

```
1. beforeTransition hooks
2. Déplacement du modèle (detach/attach)
3. Assignation des utilisateurs
4. Actions configurées visuellement (JSON du pivot transition)
5. TransitionEvent émis → HistoryListener + SendTransitionMessageListener
6. afterTransition hooks
7. Commit de la transaction
```

---

## Event listeners

L'événement `TransitionEvent` est émis après chaque transition. Ajoutez vos propres listeners :

```php
// EventServiceProvider
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
    $event->currentBasket;  // Panier de départ
    $event->nextBasket;     // Panier d'arrivée
    $event->model;          // Le modèle transitionné
    $event->comment;        // Le commentaire
}
```

---

## Méthodes du trait Workflowable

Le trait ajoute des méthodes directement sur le modèle :

### Relations

```php
$invoice->baskets;        // Tous les paniers (historique des statuts)
$invoice->histories;      // Toutes les entrées d'historique
$invoice->assignedUsers;  // Utilisateurs assignés
```

### `currentStatus(): ?Basket`

```php
$invoice->currentStatus(); // Raccourci sans passer par la Facade
```

### `workflowFeatures(): self`

Charge les relations nécessaires à l'affichage du workflow en une seule requête.

```php
$invoice->workflowFeatures();
// Charge : baskets.next, histories
```

### `scopeFromBasket(Basket $basket): Builder`

Scope Eloquent pour récupérer les modèles dans un panier donné.

```php
$invoices = Invoice::fromBasket($reviewBasket)->get();
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
            'invoice'     => $invoice,
            'status'      => $wf->currentStatus(),
            'nextSteps'   => $wf->nextBaskets(),
            'history'     => $wf->history(),
            'assignedTo'  => $wf->assignedUsers(),
        ]);
    }

    public function transition(Request $request, Invoice $invoice)
    {
        $request->validate([
            'basket_id' => 'required|uuid',
            'comment'   => 'nullable|string|max:255',
            'users'     => 'array',
            'users.*'   => 'uuid|exists:users,id',
        ]);

        Workflow::for($invoice)->transition(
            $request->basket_id,
            $request->comment,
            $request->users ?? []
        );

        return back()->with('success', 'Statut mis à jour');
    }
}
```

### Exemple avec filtrage par rôle

```php
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->user()->role; // ex: "manager"

        return view('dashboard', [
            'circuits' => Workflow::circuitsForRole($role),
            'baskets'  => Workflow::basketsForRole($role),
        ]);
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
            // Temps total de traitement
            'total_seconds'  => $wf->totalDuration(),

            // Temps passé par étape
            'draft_duration'  => $wf->durationInStatus('DRAFT'),
            'review_duration' => $wf->durationInStatus('REVIEW'),

            // Historique avec durée lisible
            'steps' => $wf->history()->map(fn ($h) => [
                'from'     => $h->previous_status,
                'to'       => $h->next_status,
                'duration' => $h->duration_human, // "2h 35min"
                'by'       => $h->done_by,
                'at'       => $h->created_at->toDateTimeString(),
            ]),
        ];
    }
}
```

### Exemple avec hooks et action custom

```php
// AppServiceProvider::boot()

// Empêcher la transition si le modèle n'est pas complet
Workflow::beforeTransition(function ($model, $from, $to) {
    if ($to->status === 'VALIDATED' && $model->missing_fields > 0) {
        throw new ValidationException('Champs manquants');
    }
});

// Logger après chaque transition
Workflow::afterTransition(function ($model, $from, $to, $comment) {
    activity()->performedOn($model)->log("Transition {$from->status} → {$to->status}");
});

// Action custom visible dans l'admin
Workflow::registerAction(GenerateInvoicePdfAction::class);
Workflow::registerAction(NotifyClientBySmsAction::class);
```
