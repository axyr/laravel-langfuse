<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class DatasetRunResponse
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public mixed $metadata,
        public string $datasetId,
        public string $datasetName,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: self::asString($data['id'] ?? null),
            name: self::asString($data['name'] ?? null),
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
            metadata: $data['metadata'] ?? null,
            datasetId: self::asString($data['datasetId'] ?? null),
            datasetName: self::asString($data['datasetName'] ?? null),
            createdAt: self::asString($data['createdAt'] ?? null),
            updatedAt: self::asString($data['updatedAt'] ?? null),
        );
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
