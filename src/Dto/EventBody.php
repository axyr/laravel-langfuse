<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;
use Axyr\Langfuse\Enums\ObservationLevel;

/**
 * An event is complete the moment it is created, so it is exported straight
 * away with `endTime` equal to `startTime`. A custom `id` is normalised into a
 * 16 hex character OTLP span id and a custom `traceId` into a 32 hex character
 * trace id.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
readonly class EventBody implements SerializableInterface
{
    public string $id;

    public string $startTime;

    public ?string $traceId;

    public ?string $parentObservationId;

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        ?string $id = null,
        ?string $traceId = null,
        public ?string $name = null,
        ?string $startTime = null,
        public mixed $input = null,
        public mixed $output = null,
        public ?array $metadata = null,
        public ?ObservationLevel $level = null,
        public ?string $statusMessage = null,
        ?string $parentObservationId = null,
        public ?string $version = null,
        public ?string $environment = null,
    ) {
        $this->id = IdGenerator::normalizeObservationId($id);
        $this->startTime = $startTime ?? IdGenerator::timestamp();
        $this->traceId = $traceId === null ? null : IdGenerator::normalizeTraceId($traceId);
        $this->parentObservationId = $parentObservationId === null
            ? null
            : IdGenerator::normalizeObservationId($parentObservationId);
    }

    public function withTraceId(string $traceId): self
    {
        return $this->withContext($traceId, $this->parentObservationId);
    }

    public function withContext(string $traceId, ?string $parentObservationId): self
    {
        return $this->copy($traceId, $parentObservationId, $this->environment);
    }

    /**
     * Returns a copy stamped with the given environment when this body has none.
     */
    public function withEnvironment(?string $environment): self
    {
        if ($environment === null || $this->environment !== null) {
            return $this;
        }

        return $this->copy($this->traceId, $this->parentObservationId, $environment);
    }

    private function copy(?string $traceId, ?string $parentObservationId, ?string $environment): self
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
            environment: $environment,
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
