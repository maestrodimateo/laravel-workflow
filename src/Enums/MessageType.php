<?php

namespace Maestrodimateo\Workflow\Enums;

enum MessageType: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case NOTIFICATION = 'notification';
}
