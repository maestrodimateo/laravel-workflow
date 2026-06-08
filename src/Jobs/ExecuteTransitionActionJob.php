<?php

namespace Maestrodimateo\Workflow\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Models\Basket;

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
 * The dispatcher in {@see \Maestrodimateo\Workflow\WorkflowManager::executeTransitionActions()}
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
    ) {}

    /**
     * Resolve the action through the container and run it on the worker.
     */
    public function handle(): void
    {
        /** @var TransitionAction $action */
        $action = app($this->actionClass);

        $action->execute($this->subject, $this->from, $this->to, $this->config);
    }
}
