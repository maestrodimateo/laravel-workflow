<?php

use Maestrodimateo\Workflow\Facades\Workflow;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\Circuit;
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
        ->and($circuit->baskets()->first()->name)->toBe('Brouillon');
});

it('can add baskets to a circuit and link transitions', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();

    $review = $circuit->baskets()->create(['name' => 'En révision', 'status' => 'REVIEW', 'color' => '#30638E']);
    $done = $circuit->baskets()->create(['name' => 'Validé',      'status' => 'DONE',   'color' => '#43A047']);

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
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#30638E']);
    $draft->next()->attach($review);

    $model = Test::create(['name' => 'Invoice #004']);

    $next = Workflow::for($model)->nextBaskets();

    expect($next)->toHaveCount(1)
        ->and($next->first()->status)->toBe('REVIEW');
});

it('transitions a model to the next basket', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#30638E']);
    $draft->next()->attach($review);

    $model = Test::create(['name' => 'Invoice #005']);

    Workflow::for($model)->transition($review->id, 'Sending to review');

    $model->load('baskets');

    expect($model->currentStatus()->status)->toBe('REVIEW');
});

it('records a history entry after a transition', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#30638E']);
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
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#30638E']);
    $done = $circuit->baskets()->create(['name' => 'Done',   'status' => 'DONE',   'color' => '#43A047']);
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
