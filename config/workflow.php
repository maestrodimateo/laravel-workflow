<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    | Configuration for the workflow public API and administration routes.
    |
    | The middleware groups below are applied as-is. Authentication is enabled
    | by default (`auth`) so the package is NOT publicly exposed out of the box.
    | Adjust the guard to match your application (e.g. `auth:sanctum` for a
    | token API). The authorization gate below is appended automatically on
    | top of these middleware for every route.
    */
    'routes' => [
        'prefix' => 'workflow',
        'middleware' => ['api', 'auth'],
        'admin_middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    | Every workflow route (public API + admin) and every write FormRequest is
    | guarded by this Gate ability. Define it in your application (e.g. in a
    | service provider) to control who may manage workflows:
    |
    |     Gate::define('manage-workflow', fn ($user) => $user->isAdmin());
    |
    | If you do not define it, the package falls back to a safe default that
    | ONLY grants access in the `local` environment, keeping production locked
    | down until you provide your own policy. Set `gate` to null to disable the
    | authorization layer entirely (NOT recommended).
    */
    'authorization' => [
        'gate' => 'manage-workflow',
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

        // Retry policy for queued actions. Queues are at-least-once, so a
        // transient failure (SMTP timeout, webhook 5xx) is retried with the
        // backoff delays below. Make non-idempotent side effects safe to retry.
        'tries' => (int) env('WORKFLOW_ACTIONS_TRIES', 3),
        'backoff' => [10, 30, 60],
        'timeout' => (int) env('WORKFLOW_ACTIONS_TIMEOUT', 30),
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

    /*
    |--------------------------------------------------------------------------
    | Webhook Action (SSRF protection)
    |--------------------------------------------------------------------------
    | Outbound webhook URLs configured on transitions are validated before the
    | request is sent server-side, to prevent Server-Side Request Forgery.
    |
    | - allowed_schemes: URL schemes accepted (default: https only).
    | - allowed_hosts: when non-empty, ONLY these hosts are callable (allow-list).
    | - block_private_ranges: reject hosts resolving to private/loopback/
    |   link-local/reserved IPs (e.g. 127.0.0.1, 10.0.0.0/8, 169.254.169.254).
    | - timeout: request timeout in seconds.
    */
    'webhook' => [
        'allowed_schemes' => ['https'],
        'allowed_hosts' => [],
        'block_private_ranges' => true,
        'timeout' => 5,
    ],

];
