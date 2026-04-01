<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\PromptInterface;

readonly class TextPrompt implements PromptInterface
{
    /**
     * @param array<string, mixed> $config
     * @param array<string> $labels
     */
    public function __construct(
        private string $name,
        private int $version,
        private string $prompt,
        private array $config = [],
        private array $labels = [],
        private bool $fallback = false,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    public function isFallback(): bool
    {
        return $this->fallback;
    }

    /**
     * @param array<string, string> $variables
     */
    public function compile(array $variables = []): string
    {
        return preg_replace_callback(
            '/\{\{(\s*\w+\s*)\}\}/',
            fn(array $matches): string => $variables[trim($matches[1])] ?? $matches[0],
            $this->prompt,
        ) ?? $this->prompt;
    }

    public function toLinkMetadata(): array
    {
        return [
            'promptName' => $this->name,
            'promptVersion' => $this->version,
        ];
    }
}
