<?php

namespace Maestrodimateo\Workflow\Conditions;

use Illuminate\Database\Eloquent\Model;
use Maestrodimateo\Workflow\Contracts\TransitionCondition;

/**
 * Built-in declarative condition: compares one model attribute against a value.
 *
 * Config shape: { field: string, op: string, value: mixed }.
 * Powers the no-code "attribute" rule editor in the designer. For anything the
 * operators below can't express, register a custom {@see TransitionCondition}.
 *
 * Supported operators: = != < <= > >= in not_in empty not_empty contains.
 */
class AttributeCondition implements TransitionCondition
{
    public static function key(): string
    {
        return 'attribute';
    }

    public static function label(): string
    {
        return 'Model attribute';
    }

    public function passes(Model $model, array $config = []): bool
    {
        $field = $config['field'] ?? null;

        // Nothing configured yet → don't block (no-op guard).
        if (! $field) {
            return true;
        }

        $actual = data_get($model, $field);
        $expected = $config['value'] ?? null;

        return match ($config['op'] ?? '=') {
            '=' => $actual == $expected,
            '!=' => $actual != $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            'in' => in_array($actual, $this->list($expected), false),
            'not_in' => ! in_array($actual, $this->list($expected), false),
            'empty' => empty($actual),
            'not_empty' => ! empty($actual),
            'contains' => is_string($actual) && str_contains($actual, (string) $expected),
            default => true,
        };
    }

    /**
     * Normalise an "in"/"not_in" value to a list: an array stays as-is, a
     * string is split on commas (so the designer's single text input works).
     *
     * @return array<int, mixed>
     */
    private function list(mixed $value): array
    {
        return is_array($value) ? $value : array_map('trim', explode(',', (string) $value));
    }

    public function reason(array $config = []): string
    {
        $value = $config['value'] ?? '';

        return __('workflow::workflow.exceptions.attribute_not_met', [
            'field' => $config['field'] ?? 'field',
            'op' => $config['op'] ?? '=',
            'value' => is_array($value) ? implode(', ', $value) : $value,
        ]);
    }
}
