<?php

namespace Maestrodimateo\Workflow\Enums;

enum RecipientType: string
{
    /**
     * The subject of the request
     */
    case SUBJECT = 'subject';

    /**
     * The operators handling the request
     */
    case OPERATORS = 'operators';
}
