<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Maestrodimateo\Workflow\Contracts\AfterCommitAction;
use Maestrodimateo\Workflow\Contracts\QueueableAction;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Facades\Workflow;
use Maestrodimateo\Workflow\Jobs\ExecuteTransitionActionJob;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Tests\Fixtures\TestModel as Test;
use Maestrodimateo\Workflow\WorkflowManager;

/**
 * Test action that records every synchronous invocation so tests can
 * assert whether a transition ran the action inline or routed it through
 * the queue.
 */
class SyncFlagAction implements TransitionAction
{
    public static array $invocations = [];

    public static function key(): string
    {
        return 'sync_flag';
    }

    public static function label(): string
    {
        return 'Sync flag';
    }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        self::$invocations[] = $model->getKey();
    }
}

/**
 * Test action that opts into queue dispatch with custom queue + connection
 * names so tests can verify they are forwarded to the dispatched job.
 */
class QueueableFlagAction implements QueueableAction, TransitionAction
{
    public static array $invocations = [];

    public static function key(): string
    {
        return 'queueable_flag';
    }

    public static function label(): string
    {
        return 'Queueable flag';
    }

    public static function queue(): ?string
    {
        return 'workflow-actions';
    }

    public static function connection(): ?string
    {
        return null;
    }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        self::$invocations[] = $model->getKey();
    }
}

/**
 * Test action implementing AfterCommitAction (no queue) so we can confirm
 * the existing after-commit branch is preserved alongside the new queueable
 * branch.
 */
class AfterCommitFlagAction implements AfterCommitAction, TransitionAction
{
    public static array $invocations = [];

    public static function key(): string
    {
        return 'after_commit_flag';
    }

    public static function label(): string
    {
        return 'After commit flag';
    }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        self::$invocations[] = $model->getKey();
    }
}

beforeEach(function () {
    SyncFlagAction::$invocations = [];
    QueueableFlagAction::$invocations = [];
    AfterCommitFlagAction::$invocations = [];

    WorkflowManager::registerAction(SyncFlagAction::class);
    WorkflowManager::registerAction(QueueableFlagAction::class);
    WorkflowManager::registerAction(AfterCommitFlagAction::class);
});

/**
 * Build a circuit with a DRAFT → REVIEW transition carrying the given
 * action keys on its pivot, then return the target REVIEW basket.
 */
function makeTransitionWithActions(array $actionKeys): Basket
{
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create([
        'name' => 'Review',
        'status' => 'REVIEW',
        'color' => '#2563eb',
    ]);

    $actionsPayload = array_map(
        fn (string $key) => ['type' => $key, 'config' => []],
        $actionKeys,
    );

    $draft->next()->attach($review, [
        'actions' => json_encode($actionsPayload, JSON_THROW_ON_ERROR),
    ]);

    return $review;
}

it('dispatches a QueueableAction as a job instead of running it inline', function () {
    Bus::fake();

    $review = makeTransitionWithActions(['queueable_flag']);
    $model = Test::create(['name' => 'Invoice #Q1']);

    Workflow::for($model)->transition($review->id);

    Bus::assertDispatched(ExecuteTransitionActionJob::class, function ($job) use ($model) {
        return $job->actionClass === QueueableFlagAction::class
            && $job->subject->is($model);
    });

    // Worker side wasn't actually run, so handle() never fired.
    expect(QueueableFlagAction::$invocations)->toBe([]);
});

it('forwards the action queue and connection to the dispatched job', function () {
    Bus::fake();

    config()->set('workflow.actions_queue.connection', 'redis');

    $review = makeTransitionWithActions(['queueable_flag']);
    $model = Test::create(['name' => 'Invoice #Q2']);

    Workflow::for($model)->transition($review->id);

    Bus::assertDispatched(ExecuteTransitionActionJob::class, function ($job) {
        return $job->queue === 'workflow-actions'
            && $job->connection === 'redis';
    });
});

it('runs a sync action inline within the transition', function () {
    Bus::fake();

    $review = makeTransitionWithActions(['sync_flag']);
    $model = Test::create(['name' => 'Invoice #S1']);

    Workflow::for($model)->transition($review->id);

    expect(SyncFlagAction::$invocations)->toBe([$model->getKey()]);
    Bus::assertNotDispatched(ExecuteTransitionActionJob::class);
});

it('runs an AfterCommitAction inline after commit, not via the queue', function () {
    Bus::fake();

    $review = makeTransitionWithActions(['after_commit_flag']);
    $model = Test::create(['name' => 'Invoice #A1']);

    Workflow::for($model)->transition($review->id);

    expect(AfterCommitFlagAction::$invocations)->toBe([$model->getKey()]);
    Bus::assertNotDispatched(ExecuteTransitionActionJob::class);
});

it('applies the configured retry policy to the dispatched job', function () {
    config()->set('workflow.actions_queue.tries', 5);
    config()->set('workflow.actions_queue.timeout', 42);

    $review = makeTransitionWithActions(['queueable_flag']);
    $model = Test::create(['name' => 'Invoice #Q3']);
    $draft = Basket::query()->where('status', 'DRAFT')->first();

    $job = new ExecuteTransitionActionJob(QueueableFlagAction::class, $model, $draft, $review);

    expect($job->tries)->toBe(5)
        ->and($job->timeout)->toBe(42)
        ->and($job->deleteWhenMissingModels)->toBeTrue()
        ->and($job->backoff())->toBe([10, 30, 60]);
});

it('executes the wrapped action when the job is processed by a worker', function () {
    $review = makeTransitionWithActions(['queueable_flag']);
    $model = Test::create(['name' => 'Invoice #W1']);
    $draft = Basket::query()->where('status', 'DRAFT')->first();

    $job = new ExecuteTransitionActionJob(
        QueueableFlagAction::class,
        $model,
        $draft,
        $review,
        ['anything' => 'goes'],
    );

    $job->handle();

    expect(QueueableFlagAction::$invocations)->toBe([$model->getKey()]);
});
