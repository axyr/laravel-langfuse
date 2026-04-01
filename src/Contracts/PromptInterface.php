<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

interface PromptInterface
{
    public function getName(): string;

    public function getVersion(): int;

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array;

    /**
     * @return array<string>
     */
    public function getLabels(): array;

    public function isFallback(): bool;

    /**
     * @param array<string, string> $variables
     * @return string|array<int, array<string, string>>
     */
    public function compile(array $variables = []): string|array;

    /**
     * @return array<string, mixed>
     */
    public function toLinkMetadata(): array;
}
