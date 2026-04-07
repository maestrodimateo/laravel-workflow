<?php

namespace Maestrodimateo\Workflow;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Events\TransitionEvent;
use Maestrodimateo\Workflow\Exceptions\ModelLockedException;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Models\WorkflowLock;
use Maestrodimateo\Workflow\Repositories\BasketRepository;
use Throwable;

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
     * Transition a single model to the next basket.
     *
     * @throws Throwable
     * @throws ModelLockedException If the model is locked by another user
     */
    public function transition(string $nextBasketId, ?string $comment = null): bool
    {
        $this->guardAgainstLock();

        $currentBasket = $this->currentStatus();
        $nextBasket = Basket::query()->findOrFail($nextBasketId);

        return DB::transaction(function () use ($currentBasket, $nextBasket, $comment) {
            $this->repository->moveModelToNextBasket($currentBasket, $nextBasket, $this->subject);

            $this->executeTransitionActions($currentBasket, $nextBasket);

            event(new TransitionEvent($currentBasket, $nextBasket, $this->subject, $comment));

            $this->unlock();

            return true;
        });
    }

    /**
     * Transition multiple models to the same basket using bulk SQL.
     *
     * Performance: ~6 queries regardless of model count (instead of ~5N).
     * Transition actions are NOT executed in bulk mode (they need per-model context).
     * Use single transition() if you need actions on each model.
     *
     *     $result = Workflow::transitionMany($invoices, $basketId, 'Batch approved');
     *     $result['transitioned']; // 8
     *     $result['skipped'];      // [['id' => '...', 'reason' => '...']]
     *
     * @param  iterable<Model>  $models  Collection or array of models
     * @param  string  $nextBasketId  Target basket UUID
     * @param  string|null  $comment  Optional comment for all transitions
     * @return array{transitioned: int, skipped: array}
     *
     * @throws Throwable
     */
    public function transitionMany(iterable $models, string $nextBasketId, ?string $comment = null): array
    {
        $nextBasket = Basket::query()->findOrFail($nextBasketId);
        $models = collect($models);

        if ($models->isEmpty()) {
            return ['transitioned' => 0, 'skipped' => []];
        }

        $modelType = $models->first()::class;
        $modelIds = $models->pluck('id')->all();
        $currentUserId = $this->currentUserId();
        $now = now();

        return DB::transaction(function () use ($modelType, $modelIds, $nextBasket, $comment, $currentUserId, $now) {

            // 1. Current basket per model (1 query)
            $assignmentQuery = DB::table('statusable')
                ->where('statusable_type', $modelType)
                ->whereIn('statusable_id', $modelIds);

            if ($this->circuitId) {
                $circuitBasketIds = Basket::where('circuit_id', $this->circuitId)->pluck('id');
                $assignmentQuery->whereIn('basket_id', $circuitBasketIds);
            }

            $currentAssignments = $assignmentQuery->get()
                ->groupBy('statusable_id')
                ->map(fn ($rows) => $rows->sortByDesc('created_at')->first());

            // 2. Locked by others (1 query)
            $lockedByOthers = DB::table('workflow_locks')
                ->where('lockable_type', $modelType)
                ->whereIn('lockable_id', $modelIds)
                ->where('expires_at', '>', $now)
                ->where('locked_by', '!=', $currentUserId)
                ->pluck('locked_by', 'lockable_id');

            // 3. Partition eligible vs skipped
            $skipped = [];
            $eligible = []; // id => current_basket_id

            foreach ($modelIds as $id) {
                if ($lockedByOthers->has($id)) {
                    $skipped[] = ['id' => $id, 'reason' => "Locked by [{$lockedByOthers[$id]}]"];
                } elseif (! $currentAssignments->has($id)) {
                    $skipped[] = ['id' => $id, 'reason' => 'No current status'];
                } else {
                    $eligible[$id] = $currentAssignments[$id]->basket_id;
                }
            }

            if (empty($eligible)) {
                return ['transitioned' => 0, 'skipped' => $skipped];
            }

            $eligibleIds = array_keys($eligible);
            $previousBasketIds = array_unique(array_values($eligible));

            // 4. Bulk detach (1 query)
            DB::table('statusable')
                ->where('statusable_type', $modelType)
                ->whereIn('statusable_id', $eligibleIds)
                ->whereIn('basket_id', $previousBasketIds)
                ->delete();

            // 5. Bulk attach (1 query)
            DB::table('statusable')->insert(
                array_map(fn ($id) => [
                    'statusable_type' => $modelType,
                    'statusable_id' => $id,
                    'basket_id' => $nextBasket->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $eligibleIds)
            );

            // 6. Bulk history insert (1 query)
            $previousStatuses = Basket::whereIn('id', $previousBasketIds)->pluck('status', 'id');

            $lastDates = DB::table('histories')
                ->where('historable_type', $modelType)
                ->whereIn('historable_id', $eligibleIds)
                ->groupBy('historable_id')
                ->selectRaw('historable_id, MAX(created_at) as last_at')
                ->pluck('last_at', 'historable_id');

            $creationDates = DB::table((new $modelType)->getTable())
                ->whereIn('id', $eligibleIds)
                ->pluck('created_at', 'id');

            DB::table('histories')->insert(
                array_map(function ($id) use ($eligible, $previousStatuses, $nextBasket, $comment, $currentUserId, $now, $lastDates, $creationDates, $modelType) {
                    $since = $lastDates[$id] ?? $creationDates[$id] ?? null;
                    $duration = $since ? (int) $now->diffInSeconds(Carbon::parse($since)) : null;

                    return [
                        'id' => Str::uuid()->toString(),
                        'historable_type' => $modelType,
                        'historable_id' => $id,
                        'previous_status' => $previousStatuses[$eligible[$id]] ?? 'UNKNOWN',
                        'next_status' => $nextBasket->status,
                        'comment' => $comment,
                        'done_by' => $currentUserId,
                        'duration_seconds' => $duration,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $eligibleIds)
            );

            // 7. Bulk release locks (1 query)
            DB::table('workflow_locks')
                ->where('lockable_type', $modelType)
                ->whereIn('lockable_id', $eligibleIds)
                ->delete();

            return ['transitioned' => count($eligibleIds), 'skipped' => $skipped];
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
    // Resource locking
    // -------------------------------------------------------------------------

    /**
     * Lock the model so no other user can transition it.
     * The lock auto-expires after the configured duration.
     *
     * @param  int|null  $minutes  Lock duration (null = use config default)
     * @return WorkflowLock The created lock
     *
     * @throws ModelLockedException If already locked by someone else
     */
    public function lock(?int $minutes = null): WorkflowLock
    {
        $this->cleanExpiredLock();

        $existingLock = $this->getActiveLock();
        $currentUserId = $this->currentUserId();

        // Already locked by someone else
        if ($existingLock && $existingLock->locked_by !== $currentUserId) {
            throw new ModelLockedException($existingLock);
        }

        // Already locked by the same user — extend it
        if ($existingLock && $existingLock->locked_by === $currentUserId) {
            $existingLock->update([
                'expires_at' => now()->addMinutes($minutes ?? config('workflow.lock.duration_minutes', 30)),
            ]);

            return $existingLock->refresh();
        }

        // Create new lock
        return $this->subject->workflowLock()->create([
            'locked_by' => $currentUserId,
            'expires_at' => now()->addMinutes($minutes ?? config('workflow.lock.duration_minutes', 30)),
        ]);
    }

    /**
     * Release the lock on the model.
     * Only the lock owner or a force unlock can release it.
     */
    public function unlock(bool $force = false): void
    {
        $lock = $this->getActiveLock();

        if (! $lock) {
            return;
        }

        if (! $force && $lock->locked_by !== $this->currentUserId()) {
            return; // Can't unlock someone else's lock without force
        }

        $lock->delete();
    }

    /**
     * Check if the model is currently locked.
     */
    public function isLocked(): bool
    {
        return $this->getActiveLock() !== null;
    }

    /**
     * Check if the model is locked by the current user.
     */
    public function isLockedByMe(): bool
    {
        $lock = $this->getActiveLock();

        return $lock && $lock->locked_by === $this->currentUserId();
    }

    /**
     * Get the user ID that holds the lock, or null.
     */
    public function lockedBy(): ?string
    {
        return $this->getActiveLock()?->locked_by;
    }

    /**
     * Get the lock expiration time, or null.
     */
    public function lockExpiration(): ?Carbon
    {
        return $this->getActiveLock()?->expires_at;
    }

    /**
     * Get the active (non-expired) lock for the model.
     */
    private function getActiveLock(): ?WorkflowLock
    {
        // Always reload to avoid stale cache after lock/unlock
        $this->subject->load('workflowLock');
        $lock = $this->subject->workflowLock;

        if (! $lock || ! $lock->isActive()) {
            return null;
        }

        return $lock;
    }

    /**
     * Delete expired locks for the model.
     */
    private function cleanExpiredLock(): void
    {
        $lock = $this->subject->workflowLock;

        if ($lock && ! $lock->isActive()) {
            $lock->delete();
            $this->subject->unsetRelation('workflowLock');
        }
    }

    /**
     * Throw if the model is locked by another user.
     */
    private function guardAgainstLock(): void
    {
        $lock = $this->getActiveLock();

        if ($lock && $lock->locked_by !== $this->currentUserId()) {
            throw new ModelLockedException($lock);
        }
    }

    /**
     * Get the current authenticated user identifier.
     */
    private function currentUserId(): string
    {
        return (string) (auth()->user()?->{config('workflow.auth_identifier', 'id')} ?? 'system');
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
