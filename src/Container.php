<?php

namespace SeoCopilot;

/**
 * Tiny PSR-11-style container.
 */
class Container
{
    /** @var array<string, callable> */
    private array $factories = [];
    /** @var array<string, mixed> */
    private array $resolved = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->resolved[$id]);
    }

    public function get(string $id)
    {
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("Service '{$id}' not registered.");
        }
        $this->resolved[$id] = ($this->factories[$id])($this);
        return $this->resolved[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
