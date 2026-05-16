<?php

namespace Maestrodimateo\Workflow\Exceptions;

use Illuminate\Support\Carbon;
use Maestrodimateo\Workflow\Models\WorkflowLock;
use RuntimeException;

class ModelLockedException extends RuntimeException
{
    public function __construct(
        public readonly WorkflowLock $lock,
    ) {
        parent::__construct(
            __('workflow::workflow.exceptions.model_locked', [
                'user' => $lock->locked_by,
                'time' => $lock->expires_at->format('H:i')
            ])
        );
    }

    public function lockedBy(): string
    {
        return $this->lock->locked_by;
    }

    public function expiresAt(): Carbon
    {
        return $this->lock->expires_at;
    }
}
