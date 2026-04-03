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
    'message_variables' => [],

];
