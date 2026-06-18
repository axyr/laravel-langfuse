<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;

readonly class CreateDatasetBody implements SerializableInterface
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public mixed $metadata = null,
        public mixed $inputSchema = null,
        public mixed $expectedOutputSchema = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'inputSchema' => $this->inputSchema,
            'expectedOutputSchema' => $this->expectedOutputSchema,
        ], fn(mixed $value): bool => $value !== null);
    }
}
