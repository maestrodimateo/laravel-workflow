<?php

namespace Maestrodimateo\Workflow\Contracts;

use Illuminate\Database\Eloquent\Model;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Support\TransitionContext;

interface TransitionAction
{
    /**
     * Unique key for this action (used in JSON config).
     */
    public static function key(): string;

    /**
     * Human-readable label shown in the admin UI.
     */
    public static function label(): string;

    /**
     * Execute the action during a transition.
     *
     * @param  TransitionContext  $context  Shared bag — read/write data for other actions in the same transition.
     */
    public function execute(Model $model, Basket $from, Basket $to, array $config = [], TransitionContext $context = new TransitionContext): void;
}
