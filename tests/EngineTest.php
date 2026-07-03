<?php

use Maestrodimateo\Workflow\Facades\Workflow;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Services\MessageVariableResolver;
use Maestrodimateo\Workflow\Tests\Fixtures\TestModel as Test;

// ---------------------------------------------------------------------------
// Duration (Carbon 3 sign)
// ---------------------------------------------------------------------------

it('records a positive duration for a transition', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $draft->next()->attach($review);

    $model = Test::create(['name' => 'Invoice #400']);
    // Backdate creation so the elapsed duration is clearly non-zero.
    $model->forceFill(['created_at' => now()->subHour()])->saveQuietly();

    Workflow::for($model)->transition($review->id);

    $duration = Workflow::for($model)->history()->first()->duration_seconds;

    expect($duration)->toBeGreaterThanOrEqual(3500);
});

// ---------------------------------------------------------------------------
// Message variable resolution
// ---------------------------------------------------------------------------

it('substitutes built-in variables in message content', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);

    $model = Test::create(['name' => 'Invoice #401']);

    $resolved = MessageVariableResolver::resolve('Moving to {{ to_name }} ({{ to_status }})', $model, $draft, $review);

    expect($resolved)->toBe('Moving to Review (REVIEW)');
});

it('html-escapes variable values when rendering into HTML', function () {
    config()->set('workflow.message_variables', [
        'payload' => fn () => '<script>alert(1)</script>',
    ]);

    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $model = Test::create(['name' => 'Invoice #402']);

    $escaped = MessageVariableResolver::resolve('{{ payload }}', $model, $draft, $draft, escapeHtml: true);
    $raw = MessageVariableResolver::resolve('{{ payload }}', $model, $draft, $draft);

    expect($escaped)->not->toContain('<script>')
        ->and($escaped)->toContain('&lt;script&gt;')
        ->and($raw)->toContain('<script>');
});

// ---------------------------------------------------------------------------
// transitionMany — bulk safety
// ---------------------------------------------------------------------------

it('skips bulk transitions that require document validation', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);

    // The DRAFT -> REVIEW transition requires a document.
    $draft->next()->attach($review, [
        'actions' => json_encode([
            ['type' => 'require_document', 'config' => ['documents' => [['type' => 'id', 'label' => 'ID']]]],
        ]),
    ]);

    $a = Test::create(['name' => 'A']);
    $b = Test::create(['name' => 'B']);

    $result = Workflow::for($a)->in($circuit)->transitionMany([$a, $b], $review->id);

    expect($result['transitioned'])->toBe(0)
        ->and($result['skipped'])->toHaveCount(2)
        ->and($result['skipped'][0]['reason'])->toBe('Requires document validation');

    // Both models stayed in DRAFT.
    $a->load('baskets');
    expect($a->currentStatus()->status)->toBe('DRAFT');
});

it('bulk transitions models when no document is required', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $draft->next()->attach($review);

    $a = Test::create(['name' => 'A']);
    $b = Test::create(['name' => 'B']);

    $result = Workflow::for($a)->in($circuit)->transitionMany([$a, $b], $review->id);

    expect($result['transitioned'])->toBe(2)
        ->and($result['skipped'])->toBeEmpty();

    $a->load('baskets');
    expect($a->currentStatus()->status)->toBe('REVIEW');
});
