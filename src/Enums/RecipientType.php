<?php

namespace Maestrodimateo\Workflow\Enums;

use Maestrodimateo\Workflow\Traits\CasesManipulation;

enum RecipientType: string
{
    use CasesManipulation;

    /**
     * L'acteur de la demande
     */
    case SUBJECT = 'subject';

    /**
     * Les opérateurs en charge de la demande directement
     */
    case OPERATORS = 'operators';
}
