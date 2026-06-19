<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class DatasetItemQuery
{
    public function __construct(
        public ?string $datasetName = null,
        public ?string $sourceTraceId = null,
        public ?string $sourceObservationId = null,
        public ?string $version = null,
        public ?int $page = null,
        public ?int $limit = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter([
            'datasetName' => $this->datasetName,
            'sourceTraceId' => $this->sourceTraceId,
            'sourceObservationId' => $this->sourceObservationId,
            'version' => $this->version,
            'page' => $this->page,
            'limit' => $this->limit,
        ], fn(mixed $value): bool => $value !== null);
    }
}
