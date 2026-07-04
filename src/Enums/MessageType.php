<?php

namespace Maestrodimateo\Workflow\Enums;

use Maestrodimateo\Workflow\Traits\CasesManipulation;

enum MessageType: string
{
    use CasesManipulation;

    case EMAIL = 'email';
    case SMS = 'sms';
    case NOTIFICATION = 'notification';
}
