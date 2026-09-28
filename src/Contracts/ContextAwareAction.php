<?php

namespace Maestrodimateo\Workflow\Contracts;

use Maestrodimateo\Workflow\Support\TransitionContext;

/**
 * Opt-in interface for actions that need to share data
 * with other actions in the same transition.
 *
 * The orchestrator injects the shared context before calling execute().
 */
interface ContextAwareAction
{
    public function setContext(TransitionContext $context): void;
}