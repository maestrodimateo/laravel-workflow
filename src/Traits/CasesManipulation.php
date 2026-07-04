<?php

namespace Maestrodimateo\Workflow\Traits;

trait CasesManipulation
{
    /**
     * Get the backing values of every case.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
