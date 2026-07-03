<?php

namespace Maestrodimateo\Workflow\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\WorkflowManager;
use Throwable;

/**
 * Queueable wrapper that runs a {@see TransitionAction} on a worker.
 *
 * The job stores only what can survive serialization safely:
 *   - the action class name (instantiated on the worker side via the container);
 *   - the subject model (Laravel's {@see SerializesModels} reduces it to type+key
 *     and re-fetches it from the DB at handle time, so the worker always works
 *     with fresh state);
 *   - the source and target {@see Basket} models (same SerializesModels behaviour);
 *   - the JSON-safe action config array.
 *
 * The dispatcher in {@see WorkflowManager::executeTransitionActions()}
 * is responsible for sending this job after the surrounding DB transaction
 * commits — otherwise the worker could race ahead and read rows that don't
 * exist yet.
 */
class ExecuteTransitionActionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of attempts before the job is marked as failed.
     */
    public int $tries;

    /**
     * Max seconds the job may run before timing out.
     */
    public int $timeout;

    /**
     * If the subject/from/to model no longer exists at handle time, discard the
     * job instead of failing it forever (SerializesModels re-fetches by key).
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  class-string<TransitionAction>  $actionClass  Fully qualified action class to run on the worker
     * @param  Model  $subject  The model going through the transition
     * @param  Basket  $from  Basket the model is leaving
     * @param  Basket  $to  Basket the model is moving to
     * @param  array  $config  JSON-safe configuration payload for the action
     */
    public function __construct(
        public string $actionClass,
        public Model $subject,
        public Basket $from,
        public Basket $to,
        public array $config = [],
    ) {
        $this->tries = (int) config('workflow.actions_queue.tries', 3);
        $this->timeout = (int) config('workflow.actions_queue.timeout', 30);
    }

    /**
     * Progressive backoff (seconds) between retries.
     *
     * @return array<int, int>|int
     */
    public function backoff(): array|int
    {
        return config('workflow.actions_queue.backoff', [10, 30, 60]);
    }

    /**
     * Resolve the action through the container and run it on the worker.
     */
    public function handle(): void
    {
        /** @var TransitionAction $action */
        $action = app($this->actionClass);

        $action->execute($this->subject, $this->from, $this->to, $this->config);
    }

    /**
     * Log the final failure once all retries are exhausted so a lost side
     * effect (unsent email, uncalled webhook) is not silent.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Workflow transition action failed permanently.', [
            'action' => $this->actionClass,
            'subject_type' => $this->subject::class,
            'subject_id' => $this->subject->getKey(),
            'from_status' => $this->from->status,
            'to_status' => $this->to->status,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
