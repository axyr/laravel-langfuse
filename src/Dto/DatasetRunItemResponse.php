<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class DatasetRunItemResponse
{
    public function __construct(
        public string $id,
        public string $datasetRunId,
        public string $datasetRunName,
        public string $datasetItemId,
        public string $traceId,
        public ?string $observationId,
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
            datasetRunId: self::asString($data['datasetRunId'] ?? null),
            datasetRunName: self::asString($data['datasetRunName'] ?? null),
            datasetItemId: self::asString($data['datasetItemId'] ?? null),
            traceId: self::asString($data['traceId'] ?? null),
            observationId: is_string($data['observationId'] ?? null) ? $data['observationId'] : null,
            createdAt: self::asString($data['createdAt'] ?? null),
            updatedAt: self::asString($data['updatedAt'] ?? null),
        );
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
