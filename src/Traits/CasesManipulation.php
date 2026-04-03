<?php

namespace Maestrodimateo\Workflow\Traits;

trait CasesManipulation
{
    /**
     * Get values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get values except provided fields
     */
    public static function except(array $exceptions): array
    {
        return array_diff(self::values(), $exceptions);
    }

    public static function only(array $fields): array
    {
        return array_intersect(self::values(), $fields);
    }

    /**
     * Check if the current enum is in the provided values
     */
    public function is(self $value): bool
    {
        return $this->value === $value->value;
    }
}
