<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

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
    ) {}

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
            'type' => $this->type,
            'traceId' => $this->traceId,
            'level' => $this->level,
            'parentObservationId' => $this->parentObservationId,
            'environment' => $this->environment,
            'fromStartTime' => $this->fromStartTime,
            'toStartTime' => $this->toStartTime,
            'version' => $this->version,
            'filter' => $this->filter,
        ], fn(mixed $value): bool => $value !== null);
    }
}
