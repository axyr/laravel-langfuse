<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * Filters for GET /api/public/experiment-items. `fromStartTime` is required.
 * Field groups: `core`, `dataset` (both default), `io`, `metadata`,
 * `itemMetadata`, `experimentMetadata`, `scores`.
 */
readonly class ExperimentItemQuery
{
    /**
     * @param  array<string>|null  $experimentId
     * @param  array<string>|null  $experimentName
     * @param  array<string>|null  $experimentItemId
     * @param  array<string>|null  $datasetId
     */
    public function __construct(
        public string $fromStartTime,
        public ?string $toStartTime = null,
        public ?array $experimentId = null,
        public ?array $experimentName = null,
        public ?array $experimentItemId = null,
        public ?array $datasetId = null,
        public ?string $filter = null,
        public ?string $fields = null,
        public ?int $limit = null,
        public ?int $scoreLimit = null,
        public ?string $cursor = null,
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function toQuery(): array
    {
        return array_filter([
            'fromStartTime' => $this->fromStartTime,
            'toStartTime' => $this->toStartTime,
            'experimentId' => QueryList::csv($this->experimentId),
            'experimentName' => QueryList::csv($this->experimentName),
            'experimentItemId' => QueryList::csv($this->experimentItemId),
            'datasetId' => QueryList::csv($this->datasetId),
            'filter' => $this->filter,
            'fields' => $this->fields,
            'limit' => $this->limit,
            'scoreLimit' => $this->scoreLimit,
            'cursor' => $this->cursor,
        ], fn(mixed $value): bool => $value !== null);
    }
}
