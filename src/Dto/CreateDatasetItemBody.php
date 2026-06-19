<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;
use Axyr\Langfuse\Enums\DatasetStatus;

readonly class CreateDatasetItemBody implements SerializableInterface
{
    public function __construct(
        public string $datasetName,
        public mixed $input = null,
        public mixed $expectedOutput = null,
        public mixed $metadata = null,
        public ?string $sourceTraceId = null,
        public ?string $sourceObservationId = null,
        public ?string $id = null,
        public ?DatasetStatus $status = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'datasetName' => $this->datasetName,
            'input' => $this->input,
            'expectedOutput' => $this->expectedOutput,
            'metadata' => $this->metadata,
            'sourceTraceId' => $this->sourceTraceId,
            'sourceObservationId' => $this->sourceObservationId,
            'id' => $this->id,
            'status' => $this->status?->value,
        ], fn(mixed $value): bool => $value !== null);
    }
}
