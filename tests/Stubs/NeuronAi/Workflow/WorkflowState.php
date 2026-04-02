<?php

declare(strict_types=1);

namespace NeuronAI\Workflow;

class WorkflowState
{
    public function __construct(protected array $data = []) {}

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }
}
