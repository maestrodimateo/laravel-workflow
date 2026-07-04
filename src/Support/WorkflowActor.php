<?php

namespace Maestrodimateo\Workflow\Support;

/**
 * Resolves the identifier of the user performing a workflow action, stored in
 * histories.done_by and used for lock ownership. Falls back to "system" when no
 * user is authenticated (queue workers, console, seeders).
 */
class WorkflowActor
{
    public static function id(): string
    {
        return (string) (auth()->user()?->{config('workflow.auth_identifier', 'id')} ?? 'system');
    }
}
