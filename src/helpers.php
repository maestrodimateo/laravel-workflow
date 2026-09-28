<?php

use Illuminate\Database\Eloquent\Model;
use Maestrodimateo\Workflow\Support\TransitionContext;
use Maestrodimateo\Workflow\WorkflowManager;

if (! function_exists('workflow')) {
    /**
     * Get the WorkflowManager instance, optionally bound to a model.
     *
     * @example workflow()->for($invoice)->transition($nextBasketId);
     * @example workflow()->for($invoice)->currentStatus();
     */
    function workflow(?Model $model = null): WorkflowManager
    {
        $manager = app(WorkflowManager::class);

        return $model ? $manager->for($model) : $manager;
    }
}

if (! function_exists('transition_context')) {
    /**
     * Access the shared context bag for the current transition.
     *
     * @example transition_context()->set('key', $value);
     * @example transition_context()->get('key', $default);
     */
    function transition_context(): TransitionContext
    {
        return app(TransitionContext::class);
    }
}
