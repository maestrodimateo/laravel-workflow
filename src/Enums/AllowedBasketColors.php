<?php

namespace Maestrodimateo\Workflow\Enums;

use Maestrodimateo\Workflow\Traits\CasesManipulation;

enum AllowedBasketColors: string
{
    use CasesManipulation;

    case SLATE = '#64748b';
    case STONE = '#78716c';
    case RED = '#e11d48';
    case ORANGE = '#ea580c';
    case AMBER = '#d97706';
    case EMERALD = '#059669';
    case TEAL = '#0d9488';
    case SKY = '#0284c7';
    case BLUE = '#2563eb';
    case INDIGO = '#4f46e5';
    case VIOLET = '#7c3aed';
    case PINK = '#db2777';
}
