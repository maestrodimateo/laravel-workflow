<?php

namespace Maestrodimateo\Workflow\Enums;

use Maestrodimateo\Workflow\Contracts\TransitionMessageDriver;
use Maestrodimateo\Workflow\Exceptions\DriverNotImplementedException;
use Maestrodimateo\Workflow\Services\EmailTransitionMessageDriver;
use Maestrodimateo\Workflow\Traits\CasesManipulation;

enum MessageType: string
{
    use CasesManipulation;

    case EMAIL = 'email';
    case SMS = 'sms';
    case NOTIFICATION = 'notification';

    public function getAttribute(): string
    {
        return match ($this) {
            self::EMAIL => 'email',
            self::SMS => 'phone',
            self::NOTIFICATION => 'notification',
        };
    }

    public function getDriver(): TransitionMessageDriver
    {
        return match ($this) {
            self::EMAIL => new EmailTransitionMessageDriver,
            default => throw DriverNotImplementedException::for($this->value),
        };
    }
}
