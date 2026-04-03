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

    /** @var array<string, class-string<TransitionAction>> */
    private static array $actions = [];

    public function __construct(private readonly BasketRepository $repository) {}

    // -------------------------------------------------------------------------
    // Action registry
    // -------------------------------------------------------------------------

    /**
     * @param  class-string<TransitionAction>  $actionClass
     */
    public static function registerAction(string $actionClass): void
    {
        static::$actions[$actionClass::key()] = $actionClass;
    }

    /**
     * @return array<string, class-string<TransitionAction>>
     */
    public static function getRegisteredActions(): array
    {
        return static::$actions;
    }

    // -------------------------------------------------------------------------
    // Model binding
    // -------------------------------------------------------------------------

    public function for(Model $model): static
    {
        $clone = clone $this;
        $clone->subject = $model;

        return $clone;
    }

    // -------------------------------------------------------------------------
    // Status & navigation
    // -------------------------------------------------------------------------

    public function currentStatus(): ?Basket
    {
        return $this->subject->baskets->last();
    }

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
    // History & duration
    // -------------------------------------------------------------------------

    public function history(): Collection
    {
        return $this->subject->histories()->latest()->get();
    }

    public function totalDuration(): int
    {
        return (int) $this->subject->histories()->sum('duration_seconds');
    }

    public function durationInStatus(string $status): int
    {
        return (int) $this->subject->histories()
            ->where('previous_status', $status)
            ->sum('duration_seconds');
    }

    // -------------------------------------------------------------------------
    // Role-based queries
    // -------------------------------------------------------------------------

    public function basketsForRole(string $role, ?string $circuitId = null): Collection
    {
        return Basket::forRole($role)
            ->when($circuitId, fn ($q) => $q->where('circuit_id', $circuitId))
            ->with('next')
            ->get();
    }

    public function basketsForRoles(array $roles, ?string $circuitId = null): Collection
    {
        return Basket::forRoles($roles)
            ->when($circuitId, fn ($q) => $q->where('circuit_id', $circuitId))
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
