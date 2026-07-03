<?php

use Maestrodimateo\Workflow\Exceptions\InvalidTransitionException;
use Maestrodimateo\Workflow\Facades\Workflow;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Models\WorkflowLock;
use Maestrodimateo\Workflow\Tests\Fixtures\TestModel as Test;

// ---------------------------------------------------------------------------
// Transition reachability guard
// ---------------------------------------------------------------------------

it('rejects a transition that skips a step in the chain', function () {
    // DRAFT -> REVIEW -> DONE. "done" has an incoming transition, so it is not
    // an entry basket and the model starts only in DRAFT.
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $done = $circuit->baskets()->create(['name' => 'Done', 'status' => 'DONE', 'color' => '#059669']);
    $draft->next()->attach($review);
    $review->next()->attach($done);

    $model = Test::create(['name' => 'Invoice #300']);

    // DRAFT can only reach REVIEW, not DONE directly.
    expect(fn () => Workflow::for($model)->transition($done->id))
        ->toThrow(InvalidTransitionException::class);

    // The model must stay in its original status.
    $model->load('baskets');
    expect($model->currentStatus()->status)->toBe('DRAFT');
});

it('rejects a transition to a basket of another circuit', function () {
    $circuitA = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $circuitB = Circuit::create(['name' => 'Compliance', 'targetModel' => Test::class]);
    $foreign = $circuitB->baskets()->first(); // DRAFT of circuit B

    $model = Test::create(['name' => 'Invoice #301']);

    expect(fn () => Workflow::for($model)->in($circuitA)->transition($foreign->id))
        ->toThrow(InvalidTransitionException::class);
});

// ---------------------------------------------------------------------------
// Lock lifecycle
// ---------------------------------------------------------------------------

it('releases the lock even when the transition fails', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $done = $circuit->baskets()->create(['name' => 'Done', 'status' => 'DONE', 'color' => '#059669']);
    $draft->next()->attach($review);
    $review->next()->attach($done);

    $model = Test::create(['name' => 'Invoice #302']);

    $wf = Workflow::for($model);
    $wf->lock();

    // Skipping REVIEW → invalid transition → fails.
    expect(fn () => $wf->transition($done->id))->toThrow(InvalidTransitionException::class);

    // The failed transition must not leave the model stuck locked.
    expect($wf->isLocked())->toBeFalse()
        ->and(WorkflowLock::count())->toBe(0);
});

it('extends the existing lock when the same user locks again', function () {
    Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $model = Test::create(['name' => 'Invoice #303']);

    $wf = Workflow::for($model);
    $first = $wf->lock(15);
    $second = $wf->lock(30);

    expect(WorkflowLock::count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($wf->isLockedByMe())->toBeTrue()
        ->and($second->expires_at->greaterThan($first->expires_at))->toBeTrue();
});
