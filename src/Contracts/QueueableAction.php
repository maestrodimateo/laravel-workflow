<?php

namespace Maestrodimateo\Workflow\Contracts;

/**
 * Marker interface for transition actions whose `execute()` should run
 * asynchronously on a Laravel queue worker instead of inside the request
 * lifecycle.
 *
 * Queueable actions are wrapped in {@see \Maestrodimateo\Workflow\Jobs\ExecuteTransitionActionJob}
 * and dispatched via `DB::afterCommit()` so the worker never picks up the
 * job before the transition's transaction has been committed (a race that
 * would otherwise let the worker read stale or non-existent rows).
 *
 * Implement this interface on actions that produce slow, non-rollbackable
 * side effects (HTTP calls, email sending, third-party API integrations).
 * Do NOT implement it on validation actions or actions that mutate DB state
 * inside the transition — those must run inline so a failure rolls back.
 *
 * QueueableAction implies after-commit semantics, so an action does not
 * need to also implement {@see AfterCommitAction}.
 */
interface QueueableAction extends TransitionAction
{
    /**
     * Name of the queue to dispatch the action onto.
     *
     * Return null to fall back to the package default
     * (`config('workflow.actions_queue.queue')`), then to Laravel's
     * default queue.
     */
    public static function queue(): ?string;

    /**
     * Name of the queue connection to use (e.g. "redis", "sqs").
     *
     * Return null to fall back to the package default
     * (`config('workflow.actions_queue.connection')`), then to Laravel's
     * default connection.
     */
    public static function connection(): ?string;
}