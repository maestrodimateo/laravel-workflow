<?php

namespace Maestrodimateo\Workflow\Support;

/**
 * Shared bag passed to every action during a single transition.
 *
 * Upstream actions write with set(), downstream actions read with get().
 */
class TransitionContext
{
    public function __construct(protected array $data = []) {}

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