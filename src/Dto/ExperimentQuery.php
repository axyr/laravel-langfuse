<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * Filters for GET /api/public/experiments. `fromStartTime` is required.
 * Field groups: `core` (default), `metadata`, `scores`.
 */
readonly class ExperimentQuery
{
    /**
     * @param  array<string>|null  $id
     * @param  array<string>|null  $name
     * @param  array<string>|null  $datasetId
     */
    public function __construct(
        public string $fromStartTime,
        public ?string $toStartTime = null,
        public ?array $id = null,
        public ?array $name = null,
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
            'id' => QueryList::csv($this->id),
            'name' => QueryList::csv($this->name),
            'datasetId' => QueryList::csv($this->datasetId),
            'filter' => $this->filter,
            'fields' => $this->fields,
            'limit' => $this->limit,
            'scoreLimit' => $this->scoreLimit,
            'cursor' => $this->cursor,
        ], fn(mixed $value): bool => $value !== null);
    }
}
