<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    | Configuration for the workflow administration API routes.
    */
    'routes' => [
        'prefix' => 'workflow',
        'middleware' => ['api'],
        'admin_middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    | The attribute used to identify who performed a transition.
    | This value is read from auth()->user() and stored in histories.done_by.
    */
    'auth_identifier' => 'id',

    /*
    |--------------------------------------------------------------------------
    | Message Variables
    |--------------------------------------------------------------------------
    | Variables available in message templates.
    | Each entry maps a placeholder key to a closure receiving ($model, $from, $to).
    | These can be used in message content as {{ key }}.
    |
    | Example:
    | 'message_variables' => [
    |     'nom_demandeur' => fn ($model) => $model->user->name,
    |     'reference'     => fn ($model) => $model->reference,
    | ],
    */
    'message_variables' => [
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Transition Actions
    |--------------------------------------------------------------------------
    | Register your own transition actions here. Each entry must be a
    | fully qualified class name extending TransitionAction.
    |
    | Example:
    | 'actions' => [
    |     App\Workflow\Actions\CreateAcademicYearAction::class,
    |     App\Workflow\Actions\NotifySlackAction::class,
    | ],
    */
    'actions' => [],

    /*
    |--------------------------------------------------------------------------
    | Queued Actions
    |--------------------------------------------------------------------------
    | Default queue and connection used when an action implements
    | QueueableAction without overriding queue() / connection() itself.
    | Leave null to fall back to Laravel's default queue/connection
    | (driven by QUEUE_CONNECTION).
    |
    | Setting `connection` to "sync" forces queueable actions to run
    | inline on the request — useful for local development without a
    | running worker.
    */
    'actions_queue' => [
        'queue' => env('WORKFLOW_ACTIONS_QUEUE'),
        'connection' => env('WORKFLOW_ACTIONS_QUEUE_CONNECTION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Locking
    |--------------------------------------------------------------------------
    | When an operator locks a model, no one else can transition it
    | until the lock expires or is released.
    */
    'lock' => [
        'duration_minutes' => (int) env('WORKFLOW_LOCK_DURATION', 30),
    ],

];
