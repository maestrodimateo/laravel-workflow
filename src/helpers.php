<?php

use Illuminate\Database\Eloquent\Model;
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
