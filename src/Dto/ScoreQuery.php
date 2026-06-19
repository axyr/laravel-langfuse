<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * Mirrors the many optional filters of GET /api/public/v2/scores; a wide
 * constructor of nullable named parameters is intentional.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
readonly class ScoreQuery
{
    /**
     * @param  array<string>|null  $environment
     * @param  array<string>|null  $traceTags
     */
    public function __construct(
        public ?int $page = null,
        public ?int $limit = null,
        public ?string $userId = null,
        public ?string $name = null,
        public ?string $fromTimestamp = null,
        public ?string $toTimestamp = null,
        public ?array $environment = null,
        public ?string $source = null,
        public ?string $operator = null,
        public ?float $value = null,
        public ?string $scoreIds = null,
        public ?string $configId = null,
        public ?string $sessionId = null,
        public ?string $datasetRunId = null,
        public ?string $traceId = null,
        public ?string $observationId = null,
        public ?string $queueId = null,
        public ?string $dataType = null,
        public ?array $traceTags = null,
        public ?string $fields = null,
        public ?string $filter = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter([
            'page' => $this->page,
            'limit' => $this->limit,
            'userId' => $this->userId,
            'name' => $this->name,
            'fromTimestamp' => $this->fromTimestamp,
            'toTimestamp' => $this->toTimestamp,
            'environment' => $this->environment,
            'source' => $this->source,
            'operator' => $this->operator,
            'value' => $this->value,
            'scoreIds' => $this->scoreIds,
            'configId' => $this->configId,
            'sessionId' => $this->sessionId,
            'datasetRunId' => $this->datasetRunId,
            'traceId' => $this->traceId,
            'observationId' => $this->observationId,
            'queueId' => $this->queueId,
            'dataType' => $this->dataType,
            'traceTags' => $this->traceTags,
            'fields' => $this->fields,
            'filter' => $this->filter,
        ], fn(mixed $value): bool => $value !== null);
    }
}
