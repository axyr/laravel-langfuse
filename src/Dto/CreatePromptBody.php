<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;

readonly class CreatePromptBody implements SerializableInterface
{
    /**
     * @param  string  $name  Prompt name
     * @param  string  $type  Prompt type: "text" or "chat"
     * @param  string|array<int, array<string, string>>  $prompt  Text content or chat messages
     * @param  array<string, mixed>|null  $config  Optional model config
     * @param  array<string>|null  $labels  Optional labels
     */
    public function __construct(
        public string $name,
        public string $type,
        public string|array $prompt,
        public ?array $config = null,
        public ?array $labels = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'prompt' => $this->prompt,
            'config' => $this->config,
            'labels' => $this->labels,
        ], fn(mixed $value): bool => $value !== null);
    }
}
