<?php

namespace Maestrodimateo\Workflow\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * A guard that decides whether a transition into a basket is allowed for a
 * given model, based on the model's own state.
 *
 * Unlike a {@see TransitionAction} (which *does* something), a condition only
 * *answers* — so it MUST be side-effect free (read-only). It is evaluated in
 * two places with the same result:
 *   - server-side in {@see \Maestrodimateo\Workflow\WorkflowManager::transition()},
 *     where a failing condition blocks the move (exception + rollback);
 *   - via {@see \Maestrodimateo\Workflow\WorkflowManager::availableTransitions()},
 *     so a consumer UI can hide/disable transitions that are not open.
 *
 * A condition may optionally declare a static `models(): array` (list of
 * target-model FQCNs) to limit it to specific circuits — same convention as
 * transition actions. No method (or an empty array) means transversal.
 */
interface TransitionCondition
{
    /** Unique key for this condition (used in the JSON config). */
    public static function key(): string;

    /** Human-readable label shown in the admin UI. */
    public static function label(): string;

    /**
     * Whether the transition is allowed for this model. MUST be side-effect free.
     *
     * @param  array<string, mixed>  $config
     */
    public function passes(Model $model, array $config = []): bool;

    /**
     * Human-readable reason shown when the condition blocks the transition.
     *
     * @param  array<string, mixed>  $config
     */
    public function reason(array $config = []): string;
}
