<?php

namespace Maestrodimateo\Workflow;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Events\TransitionEvent;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Repositories\BasketRepository;

class WorkflowManager
{
    private Model $subject;

    private ?string $circuitId = null;

    /** @var array<string, class-string<TransitionAction>> */
    private static array $actions = [];

    public function __construct(private readonly BasketRepository $repository) {}

    // -------------------------------------------------------------------------
    // Action registry
    // -------------------------------------------------------------------------

    /** @param  class-string<TransitionAction>  $actionClass */
    public static function registerAction(string $actionClass): void
    {
        static::$actions[$actionClass::key()] = $actionClass;
    }

    /** @return array<string, class-string<TransitionAction>> */
    public static function getRegisteredActions(): array
    {
        return static::$actions;
    }

    // -------------------------------------------------------------------------
    // Model & circuit binding
    // -------------------------------------------------------------------------

    /**
     * Bind the manager to a model. Returns a new instance for concurrent use.
     */
    public function for(Model $model): static
    {
        $clone = clone $this;
        $clone->subject = $model;
        $clone->circuitId = null;

        return $clone;
    }

    /**
     * Scope all operations to a specific circuit.
     * Required when the model is targeted by multiple circuits.
     *
     *     Workflow::for($invoice)->in($circuitId)->currentStatus();
     *     Workflow::for($invoice)->in($circuit)->transition($basketId);
     *
     * @param  string|Circuit  $circuit  Circuit ID or Circuit instance
     */
    public function in(string|Circuit $circuit): static
    {
        $clone = clone $this;
        $clone->circuitId = $circuit instanceof Circuit ? $circuit->id : $circuit;

        return $clone;
    }

    // -------------------------------------------------------------------------
    // Status & navigation
    // -------------------------------------------------------------------------

    /**
     * Get the current basket of the model.
     * If a circuit is set via in(), returns the status in that circuit only.
     */
    public function currentStatus(): ?Basket
    {
        if ($this->circuitId) {
            return $this->subject->baskets()
                ->where('circuit_id', $this->circuitId)
                ->orderByPivot('created_at', 'desc')
                ->first();
        }

        return $this->subject->baskets->last();
    }

    /**
     * Get the baskets the model can transition to from its current status.
     */
    public function nextBaskets(): \Illuminate\Support\Collection
    {
        return $this->currentStatus()?->next()->get() ?? collect();
    }

    // -------------------------------------------------------------------------
    // Transition
    // -------------------------------------------------------------------------

    /**
     * @throws \Throwable
     */
    public function transition(string $nextBasketId, ?string $comment = null): bool
    {
        $currentBasket = $this->currentStatus();
        $nextBasket = Basket::query()->findOrFail($nextBasketId);

        return DB::transaction(function () use ($currentBasket, $nextBasket, $comment) {
            $this->repository->moveModelToNextBasket($currentBasket, $nextBasket, $this->subject);

            $this->executeTransitionActions($currentBasket, $nextBasket);

            event(new TransitionEvent($currentBasket, $nextBasket, $this->subject, $comment));

            return true;
        });
    }

    private function executeTransitionActions(Basket $from, Basket $to): void
    {
        $pivot = $from->next()->where('to_basket_id', $to->id)->first()?->pivot;
        $actions = json_decode($pivot?->actions ?? '[]', true);

        if (! is_array($actions)) {
            return;
        }

        foreach ($actions as $actionConfig) {
            $key = $actionConfig['type'] ?? null;
            $config = $actionConfig['config'] ?? [];

            if ($key && isset(static::$actions[$key])) {
                (new static::$actions[$key])->execute($this->subject, $from, $to, $config);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Requirements
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array{type: string, label: string}>
     */
    public function requiredDocuments(string $nextBasketId): array
    {
        $current = $this->currentStatus();
        if (! $current) {
            return [];
        }

        $next = $current->next()->where('to_basket_id', $nextBasketId)->first();
        if (! $next) {
            return [];
        }

        $actions = json_decode($next->pivot->actions ?? '[]', true);

        if (! is_array($actions)) {
            return [];
        }

        return collect($actions)
            ->where('type', 'require_document')
            ->flatMap(fn ($a) => $a['config']['documents'] ?? [])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{basket: Basket, label: ?string, documents: array}>
     */
    public function requirements(): array
    {
        $current = $this->currentStatus();
        if (! $current) {
            return [];
        }

        return $current->next()->get()->map(function (Basket $next) {
            $actions = json_decode($next->pivot->actions ?? '[]', true);
            $docs = is_array($actions)
                ? collect($actions)->where('type', 'require_document')->flatMap(fn ($a) => $a['config']['documents'] ?? [])->values()->all()
                : [];

            return [
                'basket' => $next,
                'label' => $next->pivot->label,
                'documents' => $docs,
            ];
        })->keyBy(fn ($item) => $item['basket']->id)->all();
    }

    // -------------------------------------------------------------------------
    // History & duration
    // -------------------------------------------------------------------------

    /**
     * Get history, optionally filtered by circuit.
     */
    public function history(): Collection
    {
        $query = $this->subject->histories()->latest();

        if ($this->circuitId) {
            $basketIds = Basket::where('circuit_id', $this->circuitId)->pluck('id');
            $query->where(function ($q) use ($basketIds) {
                $q->whereIn('previous_status', function ($sub) use ($basketIds) {
                    $sub->select('status')->from('baskets')->whereIn('id', $basketIds);
                });
            });
        }

        return $query->get();
    }

    public function totalDuration(): int
    {
        return (int) $this->history()->sum('duration_seconds');
    }

    public function durationInStatus(string $status): int
    {
        return (int) $this->subject->histories()
            ->where('previous_status', $status)
            ->sum('duration_seconds');
    }

    // -------------------------------------------------------------------------
    // Multi-circuit helpers
    // -------------------------------------------------------------------------

    /**
     * Get the current status of the model in every circuit it belongs to.
     *
     * @return array<string, array{circuit: Circuit, basket: Basket|null}>
     */
    public function allStatuses(): array
    {
        $baskets = $this->subject->baskets()->with('circuit')->get();

        return $baskets->groupBy('circuit_id')->map(function ($circuitBaskets) {
            $latest = $circuitBaskets->last();

            return [
                'circuit' => $latest->circuit,
                'basket' => $latest,
            ];
        })->all();
    }

    /**
     * Get all circuits this model is currently part of.
     */
    public function circuits(): Collection
    {
        $circuitIds = $this->subject->baskets()->pluck('circuit_id')->unique();

        return Circuit::whereIn('id', $circuitIds)->get();
    }

    // -------------------------------------------------------------------------
    // Role-based queries
    // -------------------------------------------------------------------------

    public function basketsForRole(string $role, ?string $circuitId = null): Collection
    {
        return Basket::forRole($role)
            ->when($circuitId ?? $this->circuitId, fn ($q, $id) => $q->where('circuit_id', $id))
            ->with('next')
            ->get();
    }

    public function basketsForRoles(array $roles, ?string $circuitId = null): Collection
    {
        return Basket::forRoles($roles)
            ->when($circuitId ?? $this->circuitId, fn ($q, $id) => $q->where('circuit_id', $id))
            ->with('next')
            ->get();
    }

    public function circuitsForRole(string $role): Collection
    {
        return Circuit::forRole($role)->with('baskets')->get();
    }

    public function circuitsForRoles(array $roles): Collection
    {
        return Circuit::forRoles($roles)->with('baskets')->get();
    }
}
