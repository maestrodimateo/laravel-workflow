<?php

namespace Maestrodimateo\Workflow\Exceptions;

use RuntimeException;

class DriverNotImplementedException extends RuntimeException
{
    public static function for(string $type): self
    {
        return new self("The message driver [$type] is not implemented yet.");
    }
}
