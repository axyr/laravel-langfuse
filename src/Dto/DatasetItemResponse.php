<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Enums\DatasetStatus;

readonly class DatasetItemResponse
{
    public function __construct(
        public string $id,
        public ?DatasetStatus $status,
        public mixed $input,
        public mixed $expectedOutput,
        public mixed $metadata,
        public ?string $sourceTraceId,
        public ?string $sourceObservationId,
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
        $statusValue = $data['status'] ?? null;

        return new self(
            id: self::asString($data['id'] ?? null),
            status: is_string($statusValue) ? DatasetStatus::tryFrom($statusValue) : null,
            input: $data['input'] ?? null,
            expectedOutput: $data['expectedOutput'] ?? null,
            metadata: $data['metadata'] ?? null,
            sourceTraceId: self::asNullableString($data['sourceTraceId'] ?? null),
            sourceObservationId: self::asNullableString($data['sourceObservationId'] ?? null),
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

    private static function asNullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
