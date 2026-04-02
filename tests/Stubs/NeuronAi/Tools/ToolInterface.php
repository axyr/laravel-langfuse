<?php

declare(strict_types=1);

namespace NeuronAI\Tools;

interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @return array<string, mixed>
     */
    public function getInputs(): array;

    public function getResult(): mixed;
}
