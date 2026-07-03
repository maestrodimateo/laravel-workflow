<?php

namespace Maestrodimateo\Workflow\Exceptions;

use Maestrodimateo\Workflow\Models\Basket;
use RuntimeException;

/**
 * Thrown when a transition is attempted to a basket that is not reachable
 * from the model's current status (no transition edge, different circuit,
 * or the model has no current status at all).
 */
class InvalidTransitionException extends RuntimeException
{
    public function __construct(
        public readonly ?Basket $from,
        public readonly Basket $to,
    ) {
        parent::__construct(
            __('workflow::workflow.exceptions.invalid_transition', [
                'from' => $from?->name ?? '—',
                'to' => $to->name,
            ])
        );
    }
}
