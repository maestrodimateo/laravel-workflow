<?php

namespace Maestrodimateo\Workflow;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maestrodimateo\Workflow\Contracts\AfterCommitAction;
use Maestrodimateo\Workflow\Contracts\QueueableAction;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Contracts\TransitionCondition;
use Maestrodimateo\Workflow\Events\TransitionEvent;
use Maestrodimateo\Workflow\Exceptions\InvalidTransitionException;
use Maestrodimateo\Workflow\Exceptions\ModelLockedException;
use Maestrodimateo\Workflow\Exceptions\TransitionConditionException;
use Maestrodimateo\Workflow\Jobs\ExecuteTransitionActionJob;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Models\WorkflowLock;
use Maestrodimateo\Workflow\Support\WorkflowActor;
use Throwable;

class WorkflowManager
{
    private Model $subject;

    private ?string $circuitId = null;

    /** @var array<string, class-string<TransitionAction>> */
    private static array $actions = [];

    /** @var array<string, class-string<TransitionCondition>> */
    private static array $conditions = [];

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

    /** @param  class-string<TransitionCondition>  $conditionClass */
    public static function registerCondition(string $conditionClass): void
    {
        static::$conditions[$conditionClass::key()] = $conditionClass;
    }

    /** @return array<string, class-string<TransitionCondition>> */
    public static function getRegisteredConditions(): array
    {
        return static::$conditions;
    }

    /**
     * Clear registered actions and conditions (useful for tests and Octane).
     */
    public static function resetRegistry(): void
    {
        static::$actions = [];
        static::$conditions = [];
    }

    /**
     * Target-model classes an action is limited to.
     *
     * An action is transversal (available in every circuit) by default. To
     * limit it to specific workflows, declare an optional static
     * `models(): array` returning the circuit target-model FQCNs it applies to.
     * An empty array (or no method) means transversal.
     *
     * @param  class-string<TransitionAction>  $actionClass
     * @return array<int, class-string>
     */
    public static function actionModels(string $actionClass): array
    {
        return method_exists($actionClass, 'models') ? array_values($actionClass::models()) : [];
    }

    /**
     * Whether an action is available for a circuit's target model.
     * Transversal actions (no/empty models()) are always available.
     *
     * @param  class-string<TransitionAction>  $actionClass
     */
    public static function actionAllowsModel(string $actionClass, ?string $targetModel): bool
    {
        $models = static::actionModels($actionClass);

        return $models === [] || ($targetModel !== null && in_array($targetModel, $models, true));
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
     */
    public function currentStatus(): ?Basket
    {
        return $this->subject->currentStatus($this->circuitId);
    }

    /**
     * Get the baskets the model can transition to from its current status.
     */
    public function nextBaskets(): \Illuminate\Support\Collection
    {
        return $this->currentStatus()?->next()->get() ?? collect();
    }

    /**
     * List the reachable next baskets and, for each, whether its guarding
     * conditions currently pass for the bound model — and if not, why.
     *
     * Consumers use this to hide or disable transitions in their own UI.
     * Conditions are evaluated read-only (see {@see TransitionCondition}).
     *
     * @return array<int, array{basket: Basket, label: ?string, open: bool, blockedBy: array<int, string>}>
     */
    public function availableTransitions(): array
    {
        $current = $this->currentStatus();

        if (! $current) {
            return [];
        }

        // The pivot (label + conditions) is eager-loaded by next()->get(), so
        // decode it directly instead of re-querying per basket (N+1).
        return $current->next()->get()->map(function (Basket $next) {
            $reasons = $this->evaluateDecodedConditions(
                $this->decodePivotActions($next->pivot->conditions ?? null)
            );

            return [
                'basket' => $next,
                'label' => $next->pivot->label,
                'open' => $reasons === [],
                'blockedBy' => $reasons,
            ];
        })->all();
    }

    // -------------------------------------------------------------------------
    // Transition
    // -------------------------------------------------------------------------

    /**
     * Transition a single model to the next basket.
     *
     * The whole operation runs inside one transaction that first acquires a
     * pessimistic lock on the subject row, so two concurrent transitions on
     * the same model are serialized (no double-move / double-history). The
     * target basket must be reachable from the current status, otherwise an
     * {@see InvalidTransitionException} is thrown and nothing is changed.
     *
     * Any lock held by the current user is released once the attempt
     * completes — whether it succeeds or fails — so a failed transition never
     * leaves the model stuck locked.
     *
     * @throws Throwable
     * @throws ModelLockedException If the model is locked by another user
     * @throws InvalidTransitionException If the target basket is not reachable
     */
    public function transition(string $nextBasketId, ?string $comment = null): bool
    {
        $nextBasket = Basket::query()->findOrFail($nextBasketId);

        try {
            return DB::transaction(function () use ($nextBasket, $comment) {
                // Serialize concurrent transitions on the same subject.
                $this->lockSubjectRow();

                $this->guardAgainstLock();

                $currentBasket = $this->currentStatus();
                $this->guardAgainstInvalidTransition($currentBasket, $nextBasket);
                $this->guardAgainstConditions($currentBasket, $nextBasket);

                $this->moveToBasket($currentBasket, $nextBasket);

                $this->executeTransitionActions($currentBasket, $nextBasket);

                event(new TransitionEvent($currentBasket, $nextBasket, $this->subject, $comment));

                return true;
            });
        } finally {
            $this->unlock();
        }
    }

    /**
     * Acquire a pessimistic row-level lock on the subject to serialize
     * concurrent transitions. No-op on drivers without row locking (e.g.
     * SQLite), where transactions already serialize writes.
     */
    protected function lockSubjectRow(): void
    {
        $this->subject->newQuery()
            ->whereKey($this->subject->getKey())
            ->lockForUpdate()
            ->first();
    }

    /**
     * Ensure the target basket is reachable from the current status.
     *
     * @throws InvalidTransitionException
     */
    protected function guardAgainstInvalidTransition(?Basket $currentBasket, Basket $nextBasket): void
    {
        $reachable = $currentBasket !== null
            && $currentBasket->next()->whereKey($nextBasket->id)->exists();

        if (! $reachable) {
            throw new InvalidTransitionException($currentBasket, $nextBasket);
        }
    }

    /**
     * Move the subject out of its current basket and into the next one.
     */
    protected function moveToBasket(Basket $from, Basket $to): void
    {
        $this->subject->baskets()->detach($from);
        $this->subject->baskets()->attach($to);
    }

    /**
     * Transition multiple models to the same basket using chunked bulk SQL.
     *
     * NOTE: this is a bulk administrative path. Unlike {@see transition()}, it
     * does NOT run transition actions (webhooks, emails, logs), does NOT emit
     * {@see TransitionEvent}, and does NOT evaluate transition conditions.
     * Models whose transition requires document
     * validation ({@see RequireDocumentAction}) are skipped rather than moved,
     * so the requirement is never silently bypassed — transition them one by
     * one instead.
     *
     * @param  iterable<Model>  $models  Collection or array of models
     * @param  string  $nextBasketId  Target basket UUID
     * @param  string|null  $comment  Optional comment for all transitions
     * @param  int  $chunkSize  Number of models per batch (default 1000)
     * @return array{transitioned: int, skipped: array}
     *
     * @throws Throwable
     */
    public function transitionMany(
        iterable $models,
        string $nextBasketId,
        ?string $comment = null,
        int $chunkSize = 1000,
    ): array {
        $nextBasket = Basket::query()->findOrFail($nextBasketId);
        $models = collect($models);

        if ($models->isEmpty()) {
            return ['transitioned' => 0, 'skipped' => []];
        }

        $modelType = $models->first()::class;
        $currentUserId = $this->currentUserId();
        // Resolve the circuit's basket ids once, not per chunk.
        $circuitBasketIds = $this->circuitId
            ? Basket::where('circuit_id', $this->circuitId)->pluck('id')->all()
            : null;
        $totalTransitioned = 0;
        $allSkipped = [];

        DB::transaction(function () use ($models, $modelType, $nextBasket, $comment, $currentUserId, $circuitBasketIds, $chunkSize, &$totalTransitioned, &$allSkipped) {
            $models->chunk($chunkSize)->each(function ($chunk) use ($modelType, $nextBasket, $comment, $currentUserId, $circuitBasketIds, &$totalTransitioned, &$allSkipped) {
                $result = $this->transitionChunk(
                    $chunk->pluck('id')->all(),
                    $modelType,
                    $nextBasket,
                    $comment,
                    $currentUserId,
                    $circuitBasketIds,
                );
                $totalTransitioned += $result['transitioned'];
                $allSkipped = array_merge($allSkipped, $result['skipped']);
            });
        });

        return [
            'transitioned' => $totalTransitioned,
            'skipped' => $allSkipped,
        ];
    }

    /**
     * Process a single chunk of model IDs for bulk transition.
     *
     * @param  array<int, string>|null  $circuitBasketIds  Pre-resolved circuit basket ids (scope), or null
     */
    protected function transitionChunk(
        array $modelIds,
        string $modelType,
        Basket $nextBasket,
        ?string $comment,
        string $currentUserId,
        ?array $circuitBasketIds = null,
    ): array {
        $now = now();

        // 1. Current basket per model
        $assignmentQuery = DB::table('statusable')
            ->where('statusable_type', $modelType)
            ->whereIn('statusable_id', $modelIds);

        if ($circuitBasketIds !== null) {
            $assignmentQuery->whereIn('basket_id', $circuitBasketIds);
        }

        $currentAssignments = $assignmentQuery->get()
            ->groupBy('statusable_id')
            ->map(fn ($rows) => $rows->sortByDesc('id')->first());

        // 2. Locked by others
        $lockedByOthers = DB::table('workflow_locks')
            ->where('lockable_type', $modelType)
            ->whereIn('lockable_id', $modelIds)
            ->where('expires_at', '>', $now)
            ->where('locked_by', '!=', $currentUserId)
            ->pluck('locked_by', 'lockable_id');

        // 3. Partition eligible vs skipped
        $skipped = [];
        $eligible = [];

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

        // 3b. Skip models whose transition requires document validation: bulk
        // mode cannot verify documents, so never move them silently.
        $docRequiredFrom = $this->basketsRequiringDocuments($previousBasketIds, $nextBasket->id);

        if ($docRequiredFrom !== []) {
            foreach ($eligible as $id => $fromBasketId) {
                if (in_array($fromBasketId, $docRequiredFrom, true)) {
                    $skipped[] = ['id' => $id, 'reason' => 'Requires document validation'];
                    unset($eligible[$id]);
                }
            }

            if (empty($eligible)) {
                return ['transitioned' => 0, 'skipped' => $skipped];
            }

            $eligibleIds = array_keys($eligible);
            $previousBasketIds = array_unique(array_values($eligible));
        }

        // 4. Bulk detach
        DB::table('statusable')
            ->where('statusable_type', $modelType)
            ->whereIn('statusable_id', $eligibleIds)
            ->whereIn('basket_id', $previousBasketIds)
            ->delete();

        // 5. Bulk attach
        DB::table('statusable')->insert(
            array_map(fn ($id) => [
                'statusable_type' => $modelType,
                'statusable_id' => $id,
                'basket_id' => $nextBasket->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $eligibleIds)
        );

        // 6. Bulk history insert
        $previousBaskets = Basket::whereIn('id', $previousBasketIds)->get(['id', 'status', 'name'])->keyBy('id');
        $previousStatuses = $previousBaskets->map(fn ($b) => $b->status);
        $previousLabels = $previousBaskets->map(fn ($b) => $b->name);

        $lastDates = DB::table('histories')
            ->where('historable_type', $modelType)
            ->whereIn('historable_id', $eligibleIds)
            ->groupBy('historable_id')
            ->selectRaw('historable_id, MAX(created_at) as last_at')
            ->pluck('last_at', 'historable_id');

        // Only query creation dates for models without history
        $missingIds = array_diff($eligibleIds, $lastDates->keys()->all());
        $creationDates = empty($missingIds)
            ? collect()
            : DB::table((new $modelType)->getTable())
                ->whereIn('id', $missingIds)
                ->pluck('created_at', 'id');

        DB::table('histories')->insert(
            array_map(function ($id) use ($eligible, $previousStatuses, $previousLabels, $nextBasket, $comment, $currentUserId, $now, $lastDates, $creationDates, $modelType) {
                $since = $lastDates[$id] ?? $creationDates[$id] ?? null;
                $duration = $since ? (int) Carbon::parse($since)->diffInSeconds($now) : null;

                return [
                    'id' => Str::uuid()->toString(),
                    'historable_type' => $modelType,
                    'historable_id' => $id,
                    'previous_status' => $previousStatuses[$eligible[$id]] ?? 'UNKNOWN',
                    'previous_status_label' => $previousLabels[$eligible[$id]] ?? null,
                    'next_status' => $nextBasket->status,
                    'next_status_label' => $nextBasket->name,
                    'comment' => $comment,
                    'done_by' => $currentUserId,
                    'duration_seconds' => $duration,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $eligibleIds)
        );

        // 7. Bulk release locks
        DB::table('workflow_locks')
            ->where('lockable_type', $modelType)
            ->whereIn('lockable_id', $eligibleIds)
            ->delete();

        return ['transitioned' => count($eligibleIds), 'skipped' => $skipped];
    }

    /**
     * Execute the actions configured on the transition between two baskets.
     *
     * Three execution paths exist:
     *   - {@see QueueableAction}: dispatched as an {@see ExecuteTransitionActionJob}
     *     on a worker, deferred via `DB::afterCommit()` so the worker never picks
     *     it up before the row state is visible.
     *   - {@see AfterCommitAction}: invoked inline after the transaction commits
     *     (still in the request lifecycle, but past rollback risk).
     *   - All other actions: invoked synchronously inside the current
     *     transaction, so a thrown exception rolls the transition back.
     */
    protected function executeTransitionActions(Basket $from, Basket $to): void
    {
        $actions = $this->decodeTransitionActions($from, $to);

        foreach ($actions as $actionConfig) {
            $key = $actionConfig['type'] ?? null;
            $config = $actionConfig['config'] ?? [];

            if (! $key || ! isset(static::$actions[$key])) {
                continue;
            }

            $actionClass = static::$actions[$key];
            $action = new $actionClass;
            $subject = $this->subject;

            if ($action instanceof QueueableAction) {
                $queue = $actionClass::queue() ?? config('workflow.actions_queue.queue');
                $connection = $actionClass::connection() ?? config('workflow.actions_queue.connection');

                DB::afterCommit(function () use ($actionClass, $subject, $from, $to, $config, $queue, $connection) {
                    $job = ExecuteTransitionActionJob::dispatch($actionClass, $subject, $from, $to, $config);

                    if ($queue !== null) {
                        $job->onQueue($queue);
                    }

                    if ($connection !== null) {
                        $job->onConnection($connection);
                    }
                });
            } elseif ($action instanceof AfterCommitAction) {
                DB::afterCommit(fn () => $action->execute($subject, $from, $to, $config));
            } else {
                $action->execute($subject, $from, $to, $config);
            }
        }
    }

    /**
     * Decode the actions JSON from the transition pivot between two baskets.
     *
     * @return array<int, array{type: string, config: array}>
     */
    protected function decodeTransitionActions(Basket $from, Basket $to): array
    {
        $pivot = $from->next()->where('to_basket_id', $to->id)->first()?->pivot;

        return $this->decodePivotActions($pivot?->actions);
    }

    /**
     * Decode the actions JSON stored on a transition pivot row.
     *
     * @return array<int, array{type: string, config: array}>
     */
    protected function decodePivotActions(?string $json): array
    {
        $actions = json_decode($json ?? '[]', true, 512, JSON_THROW_ON_ERROR);

        return is_array($actions) ? $actions : [];
    }

    /**
     * Return the "from" basket ids (among the given ones) whose transition to
     * $toBasketId carries a require_document action.
     *
     * @param  array<int, string>  $fromBasketIds
     * @return array<int, string>
     */
    protected function basketsRequiringDocuments(array $fromBasketIds, string $toBasketId): array
    {
        if ($fromBasketIds === []) {
            return [];
        }

        return DB::table('transition')
            ->where('to_basket_id', $toBasketId)
            ->whereIn('from_basket_id', $fromBasketIds)
            ->get(['from_basket_id', 'actions'])
            ->filter(fn ($row) => collect($this->decodePivotActions($row->actions))
                ->contains(fn ($a) => ($a['type'] ?? null) === 'require_document'))
            ->pluck('from_basket_id')
            ->all();
    }

    /**
     * Extract the list of required documents from a set of decoded actions.
     *
     * @param  array<int, array{type: string, config: array}>  $actions
     */
    protected function extractRequiredDocuments(array $actions): array
    {
        return collect($actions)
            ->where('type', 'require_document')
            ->flatMap(fn ($a) => $a['config']['documents'] ?? [])
            ->values()
            ->all();
    }

    // -------------------------------------------------------------------------
    // Transition conditions (guards)
    // -------------------------------------------------------------------------

    /**
     * Block the transition when any guarding condition fails for the subject.
     *
     * @throws TransitionConditionException
     */
    protected function guardAgainstConditions(Basket $from, Basket $to): void
    {
        $reasons = $this->evaluateConditions($from, $to);

        if ($reasons !== []) {
            throw new TransitionConditionException($from, $to, $reasons);
        }
    }

    /**
     * Evaluate the conditions guarding the transition between two baskets and
     * return the reason for each failing one ([] = the transition is allowed).
     *
     * @return array<int, string>
     */
    protected function evaluateConditions(Basket $from, Basket $to): array
    {
        return $this->evaluateDecodedConditions($this->decodeTransitionConditions($from, $to));
    }

    /**
     * Run a decoded list of conditions against the bound subject.
     *
     * @param  array<int, array{type: string, config: array}>  $conditions
     * @return array<int, string>  Reasons of the conditions that did not pass
     */
    protected function evaluateDecodedConditions(array $conditions): array
    {
        $reasons = [];

        foreach ($conditions as $entry) {
            $key = $entry['type'] ?? null;

            if (! $key || ! isset(static::$conditions[$key])) {
                continue;
            }

            $conditionClass = static::$conditions[$key];
            $condition = new $conditionClass;
            $config = $entry['config'] ?? [];

            if (! $condition->passes($this->subject, $config)) {
                $reasons[] = $condition->reason($config);
            }
        }

        return $reasons;
    }

    /**
     * Decode the conditions JSON from the transition pivot between two baskets.
     *
     * @return array<int, array{type: string, config: array}>
     */
    protected function decodeTransitionConditions(Basket $from, Basket $to): array
    {
        $pivot = $from->next()->where('to_basket_id', $to->id)->first()?->pivot;

        return $this->decodePivotActions($pivot?->conditions);
    }

    // -------------------------------------------------------------------------
    // Resource locking
    // -------------------------------------------------------------------------

    /**
     * Lock the model so no other user can transition it.
     *
     * @param  int|null  $minutes  Lock duration (null = use config default)
     * @return WorkflowLock The created lock
     *
     * @throws ModelLockedException If already locked by someone else
     */
    public function lock(?int $minutes = null): WorkflowLock
    {
        $currentUserId = $this->currentUserId();
        $expiresAt = now()->addMinutes($minutes ?? config('workflow.lock.duration_minutes', 30));

        return DB::transaction(function () use ($currentUserId, $expiresAt) {
            $this->cleanExpiredLock();
            $existingLock = $this->getActiveLock();

            // Already locked by someone else
            if ($existingLock && $existingLock->locked_by !== $currentUserId) {
                throw new ModelLockedException($existingLock);
            }

            // Already locked by the same user — extend it
            if ($existingLock) {
                $existingLock->update(['expires_at' => $expiresAt]);

                return $existingLock->refresh();
            }

            // Create a new lock. The unique(lockable_type, lockable_id) constraint
            // is the real guard against a concurrent insert slipping between the
            // check above and here (TOCTOU): translate a violation into a proper
            // ModelLockedException instead of leaking a raw QueryException.
            try {
                $lock = $this->subject->workflowLock()->create([
                    'locked_by' => $currentUserId,
                    'expires_at' => $expiresAt,
                ]);
            } catch (UniqueConstraintViolationException) {
                $this->subject->unsetRelation('workflowLock');
                $existingLock = $this->getActiveLock();

                if ($existingLock && $existingLock->locked_by !== $currentUserId) {
                    throw new ModelLockedException($existingLock);
                }

                // The winning row is ours (or already expired); reuse it.
                if ($existingLock) {
                    $existingLock->update(['expires_at' => $expiresAt]);

                    return $existingLock->refresh();
                }

                throw new ModelLockedException(
                    $this->subject->workflowLock()->firstOrFail()
                );
            }

            $this->subject->unsetRelation('workflowLock');

            return $lock;
        });
    }

    /**
     * Release the lock on the model.
     */
    public function unlock(bool $force = false): void
    {
        $lock = $this->getActiveLock();

        if (! $lock) {
            return;
        }

        if (! $force && $lock->locked_by !== $this->currentUserId()) {
            return;
        }

        $lock->delete();
        $this->subject->unsetRelation('workflowLock');
    }

    public function isLocked(): bool
    {
        return $this->getActiveLock() !== null;
    }

    public function isLockedByMe(): bool
    {
        $lock = $this->getActiveLock();

        return $lock && $lock->locked_by === $this->currentUserId();
    }

    public function lockedBy(): ?string
    {
        return $this->getActiveLock()?->locked_by;
    }

    public function lockExpiration(): ?Carbon
    {
        return $this->getActiveLock()?->expires_at;
    }

    /**
     * Get the active (non-expired) lock for the model.
     */
    protected function getActiveLock(): ?WorkflowLock
    {
        if (! $this->subject->relationLoaded('workflowLock')) {
            $this->subject->load('workflowLock');
        }

        $lock = $this->subject->workflowLock;

        if (! $lock || ! $lock->isActive()) {
            return null;
        }

        return $lock;
    }

    protected function cleanExpiredLock(): void
    {
        $this->subject->load('workflowLock');
        $lock = $this->subject->workflowLock;

        if ($lock && ! $lock->isActive()) {
            $lock->delete();
            $this->subject->unsetRelation('workflowLock');
        }
    }

    protected function guardAgainstLock(): void
    {
        $this->subject->load('workflowLock');
        $lock = $this->getActiveLock();

        if ($lock && $lock->locked_by !== $this->currentUserId()) {
            throw new ModelLockedException($lock);
        }
    }

    /**
     * Get the current authenticated user identifier.
     */
    protected function currentUserId(): string
    {
        return WorkflowActor::id();
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

        $actions = $this->decodeTransitionActions($current, Basket::find($nextBasketId));

        return $this->extractRequiredDocuments($actions);
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

        // The pivot (label + actions) is already eager-loaded by next()->get(),
        // so decode it directly instead of re-querying per basket (N+1).
        return $current->next()->get()->mapWithKeys(function (Basket $next) {
            $actions = $this->decodePivotActions($next->pivot->actions ?? null);

            return [$next->id => [
                'basket' => $next,
                'label' => $next->pivot->label,
                'documents' => $this->extractRequiredDocuments($actions),
            ]];
        })->all();
    }

    // -------------------------------------------------------------------------
    // History & duration
    // -------------------------------------------------------------------------

    public function history(): Collection
    {
        $query = $this->subject->histories()->latest();

        if ($this->circuitId) {
            $statuses = Basket::where('circuit_id', $this->circuitId)->pluck('status');
            $query->whereIn('previous_status', $statuses);
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
            $latest = $circuitBaskets->sortByDesc('pivot.id')->first();

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

    // -------------------------------------------------------------------------
    // Programmatic import (seeders, commands, etc.)
    // -------------------------------------------------------------------------

    /**
     * Import a circuit from a JSON file exported via the admin panel.
     *
     * When $overwrite is true and a circuit with the same name+targetModel
     * exists, the configuration is replaced in place. Baskets are matched by
     * status so that models already attached (statusable pivot) survive the
     * update. Removed statuses are re-mapped to the first imported basket.
     *
     * @param  string  $path       Absolute path to the exported JSON file
     * @param  bool    $overwrite  Replace existing circuit if found
     * @return Circuit The created or updated circuit with all relations loaded
     *
     * @throws \InvalidArgumentException If the file is missing or has an invalid format
     * @throws Throwable
     */
    public static function importFromJson(string $path, bool $overwrite = false): Circuit
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data) || ($data['_format'] ?? null) !== 'laravel-workflow/v1') {
            throw new \InvalidArgumentException("Invalid workflow JSON format in: {$path}");
        }

        $circuit = DB::transaction(function () use ($data, $overwrite) {
            $circuitData = $data['circuit'];
            $refMap = [];

            $existing = $overwrite
                ? Circuit::where('name', $circuitData['name'])
                    ->where('targetModel', $circuitData['targetModel'])
                    ->first()
                : null;

            if ($existing) {
                $circuit = $existing;
                $circuit->forceFill([
                    'description' => $circuitData['description'] ?? null,
                    'roles' => $circuitData['roles'] ?? [],
                ])->saveQuietly();

                // Map old baskets by status — pull() removes matched entries
                // so only deleted statuses remain after the loop.
                $oldByStatus = $circuit->baskets->pluck('id', 'status');

                // Clear transitions and messages (baskets stay for statusable)
                DB::table('transition')
                    ->whereIn('from_basket_id', $oldByStatus->values())
                    ->delete();
                $circuit->messages()->delete();

                // Upsert baskets by status — in-place update preserves statusable
                foreach ($data['baskets'] ?? [] as $b) {
                    $status = strtoupper($b['status']);
                    if ($oldByStatus->has($status)) {
                        $basket = Basket::find($oldByStatus->pull($status));
                        $basket->update([
                            'name' => $b['name'],
                            'color' => $b['color'],
                            'roles' => $b['roles'] ?? [],
                            'visitor_roles' => $b['visitor_roles'] ?? [],
                        ]);
                    } else {
                        $basket = $circuit->baskets()->create([
                            'name' => $b['name'], 'status' => $b['status'],
                            'color' => $b['color'], 'roles' => $b['roles'] ?? [],
                            'visitor_roles' => $b['visitor_roles'] ?? [],
                        ]);
                    }
                    $refMap[$b['_ref']] = $basket->id;
                }

                // Removed statuses: move attached models to first basket, then delete
                $fallback = collect($refMap)->first();
                foreach ($oldByStatus as $basketId) {
                    if ($fallback) {
                        DB::table('statusable')
                            ->where('basket_id', $basketId)
                            ->update(['basket_id' => $fallback]);
                    }
                    Basket::destroy($basketId);
                }
            } else {
                $circuit = new Circuit;
                $circuit->forceFill([
                    'name' => $circuitData['name'],
                    'targetModel' => $circuitData['targetModel'],
                    'description' => $circuitData['description'] ?? null,
                    'roles' => $circuitData['roles'] ?? [],
                ])->saveQuietly();

                foreach ($data['baskets'] ?? [] as $b) {
                    $basket = $circuit->baskets()->create([
                        'name' => $b['name'], 'status' => $b['status'],
                        'color' => $b['color'], 'roles' => $b['roles'] ?? [],
                        'visitor_roles' => $b['visitor_roles'] ?? [],
                    ]);
                    $refMap[$b['_ref']] = $basket->id;
                }
            }

            // Transitions
            foreach ($data['baskets'] ?? [] as $b) {
                $fromId = $refMap[$b['_ref']] ?? null;
                if (! $fromId) {
                    continue;
                }
                foreach ($b['transitions'] ?? [] as $t) {
                    $toId = $refMap[$t['_to_ref']] ?? null;
                    if (! $toId) {
                        continue;
                    }
                    Basket::query()->find($fromId)->next()->attach($toId, [
                        'label' => $t['label'] ?? null,
                        'actions' => json_encode($t['actions'] ?? [], JSON_THROW_ON_ERROR),
                        'conditions' => json_encode($t['conditions'] ?? [], JSON_THROW_ON_ERROR),
                    ]);
                }
            }

            // Messages
            foreach ($data['messages'] ?? [] as $m) {
                $circuit->messages()->create([
                    'subject' => $m['subject'],
                    'content' => $m['content'],
                    'type' => $m['type'],
                    'recipient' => $m['recipient'],
                    'basket_id' => $refMap[$m['_basket_ref']] ?? null,
                ]);
            }

            return $circuit;
        });

        return $circuit->load(['baskets.next', 'baskets.previous', 'baskets.messages', 'messages']);
    }
}
