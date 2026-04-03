<?php

namespace Maestrodimateo\Workflow\Exceptions;

use RuntimeException;

class DriverNotImplementedException extends RuntimeException
{
    public static function for(string $type): self
    {
        return new self("Le driver de message [$type] n'est pas encore implémenté.");
    }
}
