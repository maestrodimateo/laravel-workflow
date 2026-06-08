<?php

namespace Maestrodimateo\Workflow;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maestrodimateo\Workflow\Contracts\AfterCommitAction;
use Maestrodimateo\Workflow\Contracts\QueueableAction;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Events\TransitionEvent;
use Maestrodimateo\Workflow\Exceptions\ModelLockedException;
use Maestrodimateo\Workflow\Jobs\ExecuteTransitionActionJob;
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
     * Transition multiple models to the same basket using chunked bulk SQL.
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
        $totalTransitioned = 0;
        $allSkipped = [];

        DB::transaction(function () use ($models, $modelType, $nextBasket, $comment, $currentUserId, $chunkSize, &$totalTransitioned, &$allSkipped) {
            $models->chunk($chunkSize)->each(function ($chunk) use ($modelType, $nextBasket, $comment, $currentUserId, &$totalTransitioned, &$allSkipped) {
                $result = $this->transitionChunk(
                    $chunk->pluck('id')->all(),
                    $modelType,
                    $nextBasket,
                    $comment,
                    $currentUserId,
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
     */
    protected function transitionChunk(
        array $modelIds,
        string $modelType,
        Basket $nextBasket,
        ?string $comment,
        string $currentUserId,
    ): array {
        $now = now();

        // 1. Current basket per model
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
        $previousStatuses = Basket::whereIn('id', $previousBasketIds)->pluck('status', 'id');

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

        $actions = json_decode($pivot?->actions ?? '[]', true, 512, JSON_THROW_ON_ERROR);

        return is_array($actions) ? $actions : [];
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
        $lock = $this->subject->workflowLock()->create([
            'locked_by' => $currentUserId,
            'expires_at' => now()->addMinutes($minutes ?? config('workflow.lock.duration_minutes', 30)),
        ]);

        $this->subject->unsetRelation('workflowLock');

        return $lock;
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

        $actions = $this->decodeTransitionActions($current, Basket::find($nextBasketId));

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

        return $current->next()->get()->map(function (Basket $next) use ($current) {
            $actions = $this->decodeTransitionActions($current, $next);
            $docs = collect($actions)
                ->where('type', 'require_document')
                ->flatMap(fn ($a) => $a['config']['documents'] ?? [])
                ->values()
                ->all();

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
            $latest = $circuitBaskets->sortByDesc('pivot.created_at')->first();

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
     * @param  string  $path  Absolute path to the exported JSON file
     * @return Circuit The newly created circuit with all relations loaded
     *
     * @throws \InvalidArgumentException If the file is missing or has an invalid format
     * @throws Throwable
     */
    public static function importFromJson(string $path): Circuit
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data) || ($data['_format'] ?? null) !== 'laravel-workflow/v1') {
            throw new \InvalidArgumentException("Invalid workflow JSON format in: {$path}");
        }

        $circuit = DB::transaction(function () use ($data) {
            $circuitData = $data['circuit'];

            $circuit = new Circuit;
            $circuit->forceFill([
                'name' => $circuitData['name'],
                'targetModel' => $circuitData['targetModel'],
                'description' => $circuitData['description'] ?? null,
                'roles' => $circuitData['roles'] ?? [],
            ]);
            $circuit->saveQuietly();

            $refMap = [];
            foreach ($data['baskets'] ?? [] as $basketData) {
                $basket = $circuit->baskets()->create([
                    'name' => $basketData['name'],
                    'status' => $basketData['status'],
                    'color' => $basketData['color'],
                    'roles' => $basketData['roles'] ?? [],
                ]);
                $refMap[$basketData['_ref']] = $basket->id;
            }

            foreach ($data['baskets'] ?? [] as $basketData) {
                $fromId = $refMap[$basketData['_ref']] ?? null;
                if (! $fromId) {
                    continue;
                }

                foreach ($basketData['transitions'] ?? [] as $trans) {
                    $toId = $refMap[$trans['_to_ref']] ?? null;
                    if (! $toId) {
                        continue;
                    }

                    Basket::query()->find($fromId)->next()->attach($toId, [
                        'label' => $trans['label'] ?? null,
                        'actions' => json_encode($trans['actions'] ?? [], JSON_THROW_ON_ERROR),
                    ]);
                }
            }

            foreach ($data['messages'] ?? [] as $msgData) {
                $circuit->messages()->create([
                    'subject' => $msgData['subject'],
                    'content' => $msgData['content'],
                    'type' => $msgData['type'],
                    'recipient' => $msgData['recipient'],
                    'basket_id' => $refMap[$msgData['_basket_ref']] ?? null,
                ]);
            }

            return $circuit;
        });

        return $circuit->load(['baskets.next', 'baskets.previous', 'baskets.messages', 'messages']);
    }
}