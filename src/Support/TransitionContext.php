<?php

namespace Maestrodimateo\Workflow\Support;

/**
 * Shared bag passed to every action during a single transition.
 *
 * Actions that implement ContextAwareAction can read/write here
 * so downstream actions can access upstream results.
 */
class TransitionContext
{
    protected array $data = [];

    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function all(): array
    {
        return $this->data;
    }
}