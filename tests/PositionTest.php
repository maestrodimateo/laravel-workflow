<?php

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maestrodimateo\Workflow\Controllers\WorkflowAdminController;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Tests\Fixtures\TestModel as Test;

// ---------------------------------------------------------------------------
// Basket canvas positions (shared per circuit) — WorkflowAdminController@positions
// ---------------------------------------------------------------------------

function savePositions(Circuit $circuit, array $positions): void
{
    $request = Request::create('/', 'PATCH', ['positions' => $positions]);
    (new WorkflowAdminController)->positions($request, $circuit);
}

it('persists positions for baskets of the circuit', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#059669']);

    savePositions($circuit, [
        $draft->id => ['x' => 24, 'y' => 48],
        $review->id => ['x' => 240, 'y' => 96],
    ]);

    expect((float) $review->fresh()->position['x'])->toBe(240.0)
        ->and((float) $review->fresh()->position['y'])->toBe(96.0)
        ->and((float) $draft->fresh()->position['x'])->toBe(24.0);
});

it('rejects non-numeric coordinates', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();

    expect(fn () => savePositions($circuit, [$draft->id => ['x' => 'nope', 'y' => 1]]))
        ->toThrow(ValidationException::class);
});

it('ignores baskets not belonging to the circuit', function () {
    $mine = Circuit::create(['name' => 'Mine', 'targetModel' => Test::class]);
    $other = Circuit::create(['name' => 'Other', 'targetModel' => Test::class]);
    $foreign = $other->baskets()->first();

    savePositions($mine, [$foreign->id => ['x' => 5, 'y' => 5]]);

    expect($foreign->fresh()->position)->toBeNull();
});
