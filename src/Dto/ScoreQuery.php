<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * Filters for GET /api/public/v3/scores. List filters are comma-separated: OR
 * within one filter, AND across filters.
 *
 * Mutually exclusive: `traceId` (with `observationId`), `sessionId` and
 * `experimentId`. `observationId` requires `traceId`, because observation ids
 * are scoped to a trace. `value`, `valueMin` and `valueMax` require a single
 * `dataType`.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
readonly class ScoreQuery
{
    /**
     * @param  array<string>|null  $id
     * @param  array<string>|null  $name
     * @param  array<string>|null  $source
     * @param  array<string>|null  $dataType
     * @param  array<string>|null  $environment
     * @param  array<string>|null  $configId
     * @param  array<string>|null  $queueId
     * @param  array<string>|null  $authorUserId
     * @param  array<string|float|int>|null  $value
     * @param  array<string>|null  $traceId
     * @param  array<string>|null  $observationId
     * @param  array<string>|null  $sessionId
     * @param  array<string>|null  $experimentId
     */
    public function __construct(
        public ?int $limit = null,
        public ?string $cursor = null,
        public ?string $fields = null,
        public ?array $id = null,
        public ?array $name = null,
        public ?array $source = null,
        public ?array $dataType = null,
        public ?array $environment = null,
        public ?array $configId = null,
        public ?array $queueId = null,
        public ?array $authorUserId = null,
        public ?array $value = null,
        public ?float $valueMin = null,
        public ?float $valueMax = null,
        public ?array $traceId = null,
        public ?array $observationId = null,
        public ?array $sessionId = null,
        public ?array $experimentId = null,
        public ?string $fromTimestamp = null,
        public ?string $toTimestamp = null,
    ) {}

    /**
     * @return array<string, string|int|float>
     */
    public function toQuery(): array
    {
        return array_filter([
            'limit' => $this->limit,
            'cursor' => $this->cursor,
            'fields' => $this->fields,
            'id' => QueryList::csv($this->id),
            'name' => QueryList::csv($this->name),
            'source' => QueryList::csv($this->source),
            'dataType' => QueryList::csv($this->dataType),
            'environment' => QueryList::csv($this->environment),
            'configId' => QueryList::csv($this->configId),
            'queueId' => QueryList::csv($this->queueId),
            'authorUserId' => QueryList::csv($this->authorUserId),
            'value' => QueryList::csv($this->value),
            'valueMin' => $this->valueMin,
            'valueMax' => $this->valueMax,
            'traceId' => QueryList::csv($this->traceId),
            'observationId' => QueryList::csv($this->observationId),
            'sessionId' => QueryList::csv($this->sessionId),
            'experimentId' => QueryList::csv($this->experimentId),
            'fromTimestamp' => $this->fromTimestamp,
            'toTimestamp' => $this->toTimestamp,
        ], fn(mixed $value): bool => $value !== null);
    }
}
