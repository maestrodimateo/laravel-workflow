<?php

namespace Maestrodimateo\Workflow\Enums;

use Maestrodimateo\Workflow\Traits\CasesManipulation;

enum RecipientType: string
{
    use CasesManipulation;

    /**
     * The subject of the request
     */
    case SUBJECT = 'subject';

    /**
     * The operators handling the request
     */
    case OPERATORS = 'operators';
}
