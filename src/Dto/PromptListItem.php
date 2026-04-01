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
        /** @var string $name */
        $name = $data['name'] ?? '';

        /** @var int $version */
        $version = $data['version'] ?? 1;

        /** @var string $type */
        $type = $data['type'] ?? 'text';

        /** @var array<string> $labels */
        $labels = $data['labels'] ?? [];

        return new self(
            name: $name,
            version: $version,
            type: $type,
            labels: $labels,
        );
    }
}
