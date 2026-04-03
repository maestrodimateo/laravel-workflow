<?php

namespace Maestrodimateo\Workflow\Enums;

use Maestrodimateo\Workflow\Traits\CasesManipulation;

enum AllowedBasketColors: string
{
    use CasesManipulation;

    case RED = '#D1495B';
    case PURPLE = '#8E24AA';
    case BLUE = '#30638E';
    case GREEN = '#43A047';
    case YELLOW = '#FDD835';
    case ORANGE = '#DB5A42';
    case GREY = '#A7BED3';
}
