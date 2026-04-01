<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class PromptListItem
{
    /**
     * @param  array<string>  $labels
     */
    public function __construct(
        public string $name,
        public int $version,
        public string $type,
        public array $labels = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            version: (int) ($data['version'] ?? 1),
            type: (string) ($data['type'] ?? 'text'),
            labels: (array) ($data['labels'] ?? []),
        );
    }
}
