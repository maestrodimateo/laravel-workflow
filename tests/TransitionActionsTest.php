<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Maestrodimateo\Workflow\Actions\SendEmailAction;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Emails\TransitionMail;
use Maestrodimateo\Workflow\Exceptions\MissingDocumentsException;
use Maestrodimateo\Workflow\Exceptions\ModelLockedException;
use Maestrodimateo\Workflow\Facades\Workflow;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Models\Message;
use Maestrodimateo\Workflow\Tests\Fixtures\Document;
use Maestrodimateo\Workflow\Tests\Fixtures\TestModel as Test;
use Maestrodimateo\Workflow\WorkflowManager;

/** Sync action that always throws, to exercise transactional rollback. */
class BoomAction implements TransitionAction
{
    public static function key(): string
    {
        return 'boom';
    }

    public static function label(): string
    {
        return 'Boom';
    }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        throw new RuntimeException('boom');
    }
}

/** Build DRAFT -> REVIEW with the given actions JSON on the pivot; return REVIEW. */
function transitionWith(array $actions): array
{
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);
    $draft->next()->attach($review, ['actions' => json_encode($actions)]);

    return [$circuit, $draft, $review];
}

// ---------------------------------------------------------------------------
// Transactional rollback
// ---------------------------------------------------------------------------

it('rolls back the transition when a synchronous action throws', function () {
    WorkflowManager::registerAction(BoomAction::class);
    [, , $review] = transitionWith([['type' => 'boom', 'config' => []]]);

    $model = Test::create(['name' => 'Invoice #500']);

    expect(fn () => Workflow::for($model)->transition($review->id))
        ->toThrow(RuntimeException::class, 'boom');

    // The transition (move + history) must have been rolled back entirely.
    $model->load('baskets');
    expect($model->currentStatus()->status)->toBe('DRAFT')
        ->and(Workflow::for($model)->history())->toHaveCount(0);
});

// ---------------------------------------------------------------------------
// RequireDocumentAction
// ---------------------------------------------------------------------------

it('blocks the transition and rolls back when a required document is missing', function () {
    [, , $review] = transitionWith([
        ['type' => 'require_document', 'config' => ['documents' => [['type' => 'id_card', 'label' => 'ID Card']]]],
    ]);

    $model = Test::create(['name' => 'Invoice #501']);

    expect(fn () => Workflow::for($model)->transition($review->id))
        ->toThrow(MissingDocumentsException::class);

    $model->load('baskets');
    expect($model->currentStatus()->status)->toBe('DRAFT')
        ->and(Workflow::for($model)->history())->toHaveCount(0);
});

it('allows the transition when the required document is present', function () {
    [, , $review] = transitionWith([
        ['type' => 'require_document', 'config' => ['documents' => [['type' => 'id_card', 'label' => 'ID Card']]]],
    ]);

    $model = Test::create(['name' => 'Invoice #502']);
    Document::create(['test_id' => $model->id, 'type' => 'id_card']);

    Workflow::for($model)->transition($review->id);

    $model->load('baskets');
    expect($model->currentStatus()->status)->toBe('REVIEW');
});

// ---------------------------------------------------------------------------
// Multi-user locking (real authenticated identity)
// ---------------------------------------------------------------------------

it('enforces locking between two authenticated users and records the real actor', function () {
    [, , $review] = transitionWith([]);

    $userA = new GenericUser(['id' => 'user-A']);
    $userB = new GenericUser(['id' => 'user-B']);

    $model = Test::create(['name' => 'Invoice #503']);

    // User A locks the model.
    test()->actingAs($userA);
    Workflow::for($model)->lock();

    // User B cannot transition it.
    test()->actingAs($userB);
    expect(fn () => Workflow::for(Test::find($model->id))->transition($review->id))
        ->toThrow(ModelLockedException::class);

    // User A can, and the history attributes the action to A.
    test()->actingAs($userA);
    Workflow::for(Test::find($model->id))->transition($review->id);

    $history = Workflow::for($model)->history()->first();
    expect($history->next_status)->toBe('REVIEW')
        ->and($history->done_by)->toBe('user-A');
});

// ---------------------------------------------------------------------------
// SendEmailAction (variable resolution end to end)
// ---------------------------------------------------------------------------

it('sends a transition email to the resolved recipient with substituted variables', function () {
    Mail::fake();

    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $draft = $circuit->baskets()->first();
    $review = $circuit->baskets()->create(['name' => 'Review', 'status' => 'REVIEW', 'color' => '#2563eb']);

    $message = Message::create([
        'subject' => 'Moved to {{ to_status }}',
        'content' => '<p>Now in {{ to_name }}</p>',
        'type' => 'email',
        'recipient' => 'subject',
        'circuit_id' => $circuit->id,
    ]);

    $model = Test::create(['name' => 'Invoice #504']);

    (new SendEmailAction)->execute($model, $draft, $review, ['message_id' => $message->id]);

    Mail::assertSent(TransitionMail::class, function (TransitionMail $mail) {
        return $mail->hasTo('ops@example.test')
            && str_contains($mail->resolvedContent, 'Now in Review')
            && $mail->resolvedSubject === 'Moved to REVIEW';
    });
});
