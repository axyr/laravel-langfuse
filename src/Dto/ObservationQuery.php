<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * Filters for GET /api/public/v2/observations.
 *
 * The v4 tables are partitioned by time, so always send `fromStartTime` and
 * `toStartTime` when you can: a lookup without a window scans everything.
 * `parseIoAsJson` is gone in v2 and returns 400.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
readonly class ObservationQuery
{
    /**
     * @param  array<string>|null  $environment
     */
    public function __construct(
        public ?string $fields = null,
        public ?string $expandMetadata = null,
        public ?int $limit = null,
        public ?string $cursor = null,
        public ?string $name = null,
        public ?string $userId = null,
        public ?string $type = null,
        public ?string $traceId = null,
        public ?string $level = null,
        public ?string $parentObservationId = null,
        public ?array $environment = null,
        public ?string $fromStartTime = null,
        public ?string $toStartTime = null,
        public ?string $version = null,
        public ?string $filter = null,
        public ?string $sessionId = null,
        public ?bool $isRootObservation = null,
    ) {}

    /**
     * The structured `filter` that selects a single observation by id, which
     * replaces the deprecated GET /api/public/observations/{id}.
     */
    public static function idFilter(string $observationId): string
    {
        return json_encode(
            [['type' => 'string', 'column' => 'id', 'operator' => '=', 'value' => $observationId]],
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter([
            'fields' => $this->fields,
            'expandMetadata' => $this->expandMetadata,
            'limit' => $this->limit,
            'cursor' => $this->cursor,
            'name' => $this->name,
            'userId' => $this->userId,
            'sessionId' => $this->sessionId,
            'type' => $this->type,
            'traceId' => $this->traceId,
            'level' => $this->level,
            'parentObservationId' => $this->parentObservationId,
            'isRootObservation' => $this->isRootObservation === null
                ? null
                : ($this->isRootObservation ? 'true' : 'false'),
            'environment' => $this->environment,
            'fromStartTime' => $this->fromStartTime,
            'toStartTime' => $this->toStartTime,
            'version' => $this->version,
            'filter' => $this->filter,
        ], fn(mixed $value): bool => $value !== null);
    }
}
