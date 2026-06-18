<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class DatasetResponse
{
    public function __construct(
        public string $id,
        public string $name,
        public string $projectId,
        public string $createdAt,
        public string $updatedAt,
        public ?string $description,
        public mixed $metadata,
        public mixed $inputSchema,
        public mixed $expectedOutputSchema,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: self::asString($data['id'] ?? null),
            name: self::asString($data['name'] ?? null),
            projectId: self::asString($data['projectId'] ?? null),
            createdAt: self::asString($data['createdAt'] ?? null),
            updatedAt: self::asString($data['updatedAt'] ?? null),
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
            metadata: $data['metadata'] ?? null,
            inputSchema: $data['inputSchema'] ?? null,
            expectedOutputSchema: $data['expectedOutputSchema'] ?? null,
        );
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
