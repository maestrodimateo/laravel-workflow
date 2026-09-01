<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Maestrodimateo\Workflow\Conditions\AttributeCondition;
use Maestrodimateo\Workflow\Controllers\WorkflowAdminController;
use Maestrodimateo\Workflow\Contracts\TransitionCondition;
use Maestrodimateo\Workflow\Exceptions\TransitionConditionException;
use Maestrodimateo\Workflow\Facades\Workflow;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Tests\Fixtures\TestModel as Test;
use Maestrodimateo\Workflow\WorkflowManager;

/** A custom coded condition: passes only when the name length is even. */
class EvenNameLengthCondition implements TransitionCondition
{
    public static function key(): string
    {
        return 'even_len';
    }

    public static function label(): string
    {
        return 'Even name length';
    }

    public function passes(Model $model, array $config = []): bool
    {
        return strlen((string) $model->name) % 2 === 0;
    }

    public function reason(array $config = []): string
    {
        return 'Name length must be even.';
    }
}

/**
 * Build a circuit with DRAFT -> Done and attach the given conditions on the edge.
 *
 * @return array{0: Circuit, 1: \Maestrodimateo\Workflow\Models\Basket, 2: \Maestrodimateo\Workflow\Models\Basket}
 */
function circuitWithCondition(array $conditions): array
{
    $circuit = Circuit::create(['name' => 'C', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $done = $circuit->baskets()->create(['name' => 'Done', 'status' => 'DONE', 'color' => '#059669']);
    $draft->next()->attach($done->id, ['conditions' => json_encode($conditions)]);

    return [$circuit, $draft, $done];
}

// ---------------------------------------------------------------------------
// AttributeCondition operators
// ---------------------------------------------------------------------------

it('evaluates the attribute operators', function () {
    $c = new AttributeCondition;
    $m = new Test(['name' => 'hello']);

    expect($c->passes($m, ['field' => 'name', 'op' => '=', 'value' => 'hello']))->toBeTrue()
        ->and($c->passes($m, ['field' => 'name', 'op' => '!=', 'value' => 'hello']))->toBeFalse()
        ->and($c->passes($m, ['field' => 'name', 'op' => '>=', 'value' => 'a']))->toBeTrue()
        ->and($c->passes($m, ['field' => 'name', 'op' => 'in', 'value' => ['a', 'hello']]))->toBeTrue()
        ->and($c->passes($m, ['field' => 'name', 'op' => 'in', 'value' => 'a, hello']))->toBeTrue() // comma string
        ->and($c->passes($m, ['field' => 'name', 'op' => 'in', 'value' => 'a, b']))->toBeFalse()
        ->and($c->passes($m, ['field' => 'name', 'op' => 'not_in', 'value' => ['a', 'b']]))->toBeTrue()
        ->and($c->passes($m, ['field' => 'name', 'op' => 'contains', 'value' => 'ell']))->toBeTrue()
        ->and($c->passes($m, ['field' => 'name', 'op' => 'not_empty']))->toBeTrue()
        ->and($c->passes(new Test(['name' => null]), ['field' => 'name', 'op' => 'empty']))->toBeTrue()
        ->and($c->passes($m, []))->toBeTrue(); // nothing configured → no-op
});

// ---------------------------------------------------------------------------
// Server-side enforcement
// ---------------------------------------------------------------------------

it('blocks a transition whose condition fails and rolls back', function () {
    [, $draft, $done] = circuitWithCondition([
        ['type' => 'attribute', 'config' => ['field' => 'name', 'op' => '=', 'value' => 'OK']],
    ]);
    $model = Test::create(['name' => 'NOPE']);

    expect(fn () => Workflow::for($model)->transition($done->id))
        ->toThrow(TransitionConditionException::class);

    expect(Workflow::for($model)->currentStatus()->id)->toBe($draft->id); // unchanged
});

it('allows the transition when the condition passes', function () {
    [, , $done] = circuitWithCondition([
        ['type' => 'attribute', 'config' => ['field' => 'name', 'op' => '=', 'value' => 'OK']],
    ]);
    $model = Test::create(['name' => 'OK']);

    expect(Workflow::for($model)->transition($done->id))->toBeTrue()
        ->and(Workflow::for($model)->currentStatus()->id)->toBe($done->id);
});

it('supports a custom registered condition', function () {
    WorkflowManager::registerCondition(EvenNameLengthCondition::class);
    [, , $done] = circuitWithCondition([['type' => 'even_len', 'config' => []]]);

    expect(fn () => Workflow::for(Test::create(['name' => 'abc']))->transition($done->id))
        ->toThrow(TransitionConditionException::class); // len 3 → blocked
    expect(Workflow::for(Test::create(['name' => 'abcd']))->transition($done->id))->toBeTrue(); // len 4 → ok
});

// ---------------------------------------------------------------------------
// availableTransitions() — drives the consumer UI
// ---------------------------------------------------------------------------

it('reports availability and reasons via availableTransitions', function () {
    circuitWithCondition([
        ['type' => 'attribute', 'config' => ['field' => 'name', 'op' => '=', 'value' => 'OK']],
    ]);

    $blocked = Workflow::for(Test::create(['name' => 'NOPE']))->availableTransitions();
    expect($blocked)->toHaveCount(1)
        ->and($blocked[0]['open'])->toBeFalse()
        ->and($blocked[0]['blockedBy'])->not->toBeEmpty();

    $open = Workflow::for(Test::create(['name' => 'OK']))->availableTransitions();
    expect($open[0]['open'])->toBeTrue()
        ->and($open[0]['blockedBy'])->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Designer wiring — WorkflowAdminController@updateTransition persists conditions
// ---------------------------------------------------------------------------

it('persists conditions configured through the admin transition editor', function () {
    $circuit = Circuit::create(['name' => 'C', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $done = $circuit->baskets()->create(['name' => 'Done', 'status' => 'DONE', 'color' => '#059669']);
    $draft->next()->attach($done->id);

    $request = Request::create('/', 'PUT', [
        'label' => 'Approve',
        'conditions' => [
            ['type' => 'attribute', 'config' => ['field' => 'name', 'op' => '=', 'value' => 'OK']],
        ],
    ]);
    (new WorkflowAdminController)->updateTransition($request, $draft->fresh(), $done->fresh());

    // The saved condition now guards real transitions.
    expect(fn () => Workflow::for(Test::create(['name' => 'NO']))->transition($done->id))
        ->toThrow(TransitionConditionException::class);
    expect(Workflow::for(Test::create(['name' => 'OK']))->transition($done->id))->toBeTrue();
});

