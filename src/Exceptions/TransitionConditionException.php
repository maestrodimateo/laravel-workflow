<?php

namespace Maestrodimateo\Workflow\Exceptions;

use Maestrodimateo\Workflow\Models\Basket;
use RuntimeException;

/**
 * Thrown when a transition is blocked because one or more guarding conditions
 * did not pass for the model. The transition is rolled back; {@see $reasons}
 * carries the human-readable reason for each failed condition.
 */
class TransitionConditionException extends RuntimeException
{
    /**
     * @param  array<int, string>  $reasons
     */
    public function __construct(
        public readonly ?Basket $from,
        public readonly Basket $to,
        public readonly array $reasons,
    ) {
        parent::__construct(
            __('workflow::workflow.exceptions.condition_blocked', ['to' => $to->name])
            .' '.implode('; ', $reasons)
        );
    }
}
