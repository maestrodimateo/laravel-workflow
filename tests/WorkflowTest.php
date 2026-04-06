<?php

use Maestrodimateo\Workflow\Exceptions\ModelLockedException;
use Maestrodimateo\Workflow\Facades\Workflow;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Models\WorkflowLock;
use Maestrodimateo\Workflow\Tests\Fixtures\TestModel as Test;

// ---------------------------------------------------------------------------
// Circuit & Basket configuration
// ---------------------------------------------------------------------------

it('creates a DRAFT basket automatically when a circuit is created', function () {
    $circuit = Circuit::create([
        'name' => 'Leave Request',
        'targetModel' => Test::class,
        'description' => 'Handles leave request approvals',
    ]);

    expect($circuit->baskets()->count())->toBe(1)
        ->and($circuit->baskets()->first()->status)->toBe('DRAFT')
        ->and($circuit->baskets()->first()->name)->toBe('Draft');
});

it('can add baskets to a circuit and link transitions', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();

    $review = $circuit->baskets()->create(['name' => 'En révision', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $done = $circuit->baskets()->create(['name' => 'Validé',      'status' => 'DONE',   'color' => '#059669']);

    $draft->next()->attach($review);
    $review->next()->attach($done);

    expect($draft->next()->count())->toBe(1)
        ->and($draft->next()->first()->status)->toBe('REVIEW')
        ->and($review->next()->first()->status)->toBe('DONE');
});

// ---------------------------------------------------------------------------
// Workflowable trait
// ---------------------------------------------------------------------------

it('attaches a model to the DRAFT basket when it is created', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);

    $model = Test::create(['name' => 'Invoice #001']);

    expect($model->baskets()->count())->toBe(1)
        ->and($model->currentStatus()->status)->toBe('DRAFT');
});

// ---------------------------------------------------------------------------
// WorkflowManager / Facade / Helper
// ---------------------------------------------------------------------------

it('returns the current status via the Facade', function () {
    Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $model = Test::create(['name' => 'Invoice #002']);

    $status = Workflow::for($model)->currentStatus();

    expect($status)->toBeInstanceOf(Basket::class)
        ->and($status->status)->toBe('DRAFT');
});

it('returns the current status via the helper', function () {
    Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $model = Test::create(['name' => 'Invoice #003']);

    $status = workflow($model)->currentStatus();

    expect($status)->toBeInstanceOf(Basket::class)
        ->and($status->status)->toBe('DRAFT');
});

it('lists available next baskets', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $draft->next()->attach($review);

    $model = Test::create(['name' => 'Invoice #004']);

    $next = Workflow::for($model)->nextBaskets();

    expect($next)->toHaveCount(1)
        ->and($next->first()->status)->toBe('REVIEW');
});

it('transitions a model to the next basket', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $draft->next()->attach($review);

    $model = Test::create(['name' => 'Invoice #005']);

    Workflow::for($model)->transition($review->id, 'Sending to review');

    $model->load('baskets');

    expect($model->currentStatus()->status)->toBe('REVIEW');
});

it('records a history entry after a transition', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $draft->next()->attach($review);

    $model = Test::create(['name' => 'Invoice #006']);
    Workflow::for($model)->transition($review->id, 'Moving forward');

    $history = Workflow::for($model)->history();

    expect($history)->toHaveCount(1)
        ->and($history->first()->previous_status)->toBe('DRAFT')
        ->and($history->first()->next_status)->toBe('REVIEW')
        ->and($history->first()->comment)->toBe('Moving forward');
});

it('can perform multiple transitions', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $done = $circuit->baskets()->create(['name' => 'Done',   'status' => 'DONE',   'color' => '#059669']);
    $draft->next()->attach($review);
    $review->next()->attach($done);

    $model = Test::create(['name' => 'Invoice #007']);

    Workflow::for($model)->transition($review->id);
    $model->load('baskets');
    Workflow::for($model)->transition($done->id);
    $model->load('baskets');

    expect($model->currentStatus()->status)->toBe('DONE')
        ->and(Workflow::for($model)->history())->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// Multi-circuit support
// ---------------------------------------------------------------------------

it('attaches a model to DRAFT baskets of ALL circuits targeting it', function () {
    $circuitA = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $circuitB = Circuit::create(['name' => 'Compliance', 'targetModel' => Test::class]);

    $model = Test::create(['name' => 'Invoice #100']);

    expect($model->baskets()->count())->toBe(2);
});

it('can get the current status scoped to a specific circuit via in()', function () {
    $circuitA = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $circuitB = Circuit::create(['name' => 'Compliance', 'targetModel' => Test::class]);

    $model = Test::create(['name' => 'Invoice #101']);

    $statusA = Workflow::for($model)->in($circuitA)->currentStatus();
    $statusB = Workflow::for($model)->in($circuitB->id)->currentStatus();

    expect($statusA)->toBeInstanceOf(Basket::class)
        ->and($statusA->circuit_id)->toBe($circuitA->id)
        ->and($statusB->circuit_id)->toBe($circuitB->id);
});

it('can transition in a specific circuit without affecting the other', function () {
    $circuitA = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $circuitB = Circuit::create(['name' => 'Compliance', 'targetModel' => Test::class]);

    $reviewA = $circuitA->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $circuitA->baskets()->first()->next()->attach($reviewA);

    $model = Test::create(['name' => 'Invoice #102']);

    // Transition in circuit A only
    Workflow::for($model)->in($circuitA)->transition($reviewA->id);
    $model->load('baskets');

    // Circuit A should be in REVIEW
    $statusA = Workflow::for($model)->in($circuitA)->currentStatus();
    expect($statusA->status)->toBe('REVIEW');

    // Circuit B should still be in DRAFT
    $statusB = Workflow::for($model)->in($circuitB)->currentStatus();
    expect($statusB->status)->toBe('DRAFT');
});

it('can list all statuses across circuits via allStatuses()', function () {
    $circuitA = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $circuitB = Circuit::create(['name' => 'Compliance', 'targetModel' => Test::class]);

    $model = Test::create(['name' => 'Invoice #103']);

    $statuses = Workflow::for($model)->allStatuses();

    expect($statuses)->toHaveCount(2)
        ->and($statuses[$circuitA->id]['circuit']->id)->toBe($circuitA->id)
        ->and($statuses[$circuitB->id]['circuit']->id)->toBe($circuitB->id);
});

it('can list all circuits a model belongs to', function () {
    $circuitA = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $circuitB = Circuit::create(['name' => 'Compliance', 'targetModel' => Test::class]);

    $model = Test::create(['name' => 'Invoice #104']);

    $circuits = Workflow::for($model)->circuits();

    expect($circuits)->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// Resource locking
// ---------------------------------------------------------------------------

it('can lock and unlock a model', function () {
    Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $model = Test::create(['name' => 'Invoice #200']);

    $wf = Workflow::for($model);
    $lock = $wf->lock(15);

    expect($lock)->toBeInstanceOf(WorkflowLock::class)
        ->and($wf->isLocked())->toBeTrue()
        ->and($wf->lockedBy())->toBe('system')
        ->and($wf->lockExpiration())->not->toBeNull();

    $wf->unlock();

    expect($wf->isLocked())->toBeFalse();
});

it('allows the lock owner to transition', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $draft->next()->attach($review);

    $model = Test::create(['name' => 'Invoice #201']);

    $wf = Workflow::for($model);
    $wf->lock();
    $wf->transition($review->id);

    $model->load('baskets');

    expect($model->currentStatus()->status)->toBe('REVIEW')
        ->and($wf->isLocked())->toBeFalse(); // Lock released after transition
});

it('blocks transition when locked by another user', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $draft->next()->attach($review);

    $model = Test::create(['name' => 'Invoice #202']);

    // Simulate a lock by another user
    $model->workflowLock()->create([
        'locked_by' => 'other-user-id',
        'expires_at' => now()->addMinutes(30),
    ]);

    expect(fn () => Workflow::for($model)->transition($review->id))
        ->toThrow(ModelLockedException::class);
});

it('auto-expires locks after the configured duration', function () {
    Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $model = Test::create(['name' => 'Invoice #203']);

    // Create an expired lock
    $model->workflowLock()->create([
        'locked_by' => 'other-user-id',
        'expires_at' => now()->subMinute(),
    ]);

    // Should not be considered locked
    expect(Workflow::for($model)->isLocked())->toBeFalse();
});

it('filters unlocked models via scope', function () {
    Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $free = Test::create(['name' => 'Free']);
    $locked = Test::create(['name' => 'Locked']);

    $locked->workflowLock()->create([
        'locked_by' => 'user-1',
        'expires_at' => now()->addMinutes(30),
    ]);

    $unlocked = Test::unlocked()->pluck('name')->all();

    expect($unlocked)->toContain('Free')
        ->and($unlocked)->not->toContain('Locked');
});
