<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;
use Axyr\Langfuse\Enums\ObservationLevel;

readonly class EventBody implements SerializableInterface
{
    public string $id;

    public string $startTime;

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        ?string $id = null,
        public ?string $traceId = null,
        public ?string $name = null,
        ?string $startTime = null,
        public mixed $input = null,
        public mixed $output = null,
        public ?array $metadata = null,
        public ?ObservationLevel $level = null,
        public ?string $statusMessage = null,
        public ?string $parentObservationId = null,
        public ?string $version = null,
        public ?string $environment = null,
    ) {
        $this->id = $id ?? IdGenerator::uuid();
        $this->startTime = $startTime ?? IdGenerator::timestamp();
    }

    public function withTraceId(string $traceId): self
    {
        return $this->withContext($traceId, $this->parentObservationId);
    }

    public function withContext(string $traceId, ?string $parentObservationId): self
    {
        return new self(
            id: $this->id,
            traceId: $traceId,
            name: $this->name,
            startTime: $this->startTime,
            input: $this->input,
            output: $this->output,
            metadata: $this->metadata,
            level: $this->level,
            statusMessage: $this->statusMessage,
            parentObservationId: $parentObservationId,
            version: $this->version,
            environment: $this->environment,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'traceId' => $this->traceId,
            'name' => $this->name,
            'startTime' => $this->startTime,
            'input' => $this->input,
            'output' => $this->output,
            'metadata' => $this->metadata,
            'level' => $this->level?->value,
            'statusMessage' => $this->statusMessage,
            'parentObservationId' => $this->parentObservationId,
            'version' => $this->version,
            'environment' => $this->environment,
        ], fn(mixed $value): bool => $value !== null);
    }
}
