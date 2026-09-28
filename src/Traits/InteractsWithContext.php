<?php

namespace Maestrodimateo\Workflow\Traits;

use Maestrodimateo\Workflow\Support\TransitionContext;

/**
 * Drop-in implementation of ContextAwareAction.
 *
 * Use: `use InteractsWithContext;` — gives you $this->context() in execute().
 */
trait InteractsWithContext
{
    protected TransitionContext $transitionContext;

    public function setContext(TransitionContext $context): void
    {
        $this->transitionContext = $context;
    }

    protected function context(): TransitionContext
    {
        return $this->transitionContext ??= new TransitionContext;
    }
}