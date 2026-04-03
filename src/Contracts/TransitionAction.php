<?php

namespace Maestrodimateo\Workflow\Contracts;

use Illuminate\Database\Eloquent\Model;
use Maestrodimateo\Workflow\Models\Basket;

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
     */
    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void;
}
