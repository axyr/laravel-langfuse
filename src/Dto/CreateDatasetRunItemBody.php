<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;

readonly class CreateDatasetRunItemBody implements SerializableInterface
{
    public function __construct(
        public string $runName,
        public string $datasetItemId,
        public ?string $runDescription = null,
        public mixed $metadata = null,
        public ?string $observationId = null,
        public ?string $traceId = null,
        public ?string $datasetVersion = null,
        public ?string $createdAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'runName' => $this->runName,
            'datasetItemId' => $this->datasetItemId,
            'runDescription' => $this->runDescription,
            'metadata' => $this->metadata,
            'observationId' => $this->observationId,
            'traceId' => $this->traceId,
            'datasetVersion' => $this->datasetVersion,
            'createdAt' => $this->createdAt,
        ], fn(mixed $value): bool => $value !== null);
    }
}
