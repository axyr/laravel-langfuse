<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;
use Axyr\Langfuse\Enums\ObservationLevel;
use Axyr\Langfuse\Enums\ObservationType;

/**
 * A custom `id` is normalised into a 16 hex character OTLP span id and a custom
 * `traceId` into a 32 hex character trace id, so both keep linking to the
 * observations produced from the same values.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
readonly class SpanBody implements SerializableInterface
{
    public string $id;

    public ?string $traceId;

    public ?string $parentObservationId;

    /**
     * @param array<string, mixed>|null $metadata
     * @param ObservationType|null $type Overrides the default `span` observation type.
     */
    public function __construct(
        ?string $id = null,
        ?string $traceId = null,
        public ?string $name = null,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public mixed $input = null,
        public mixed $output = null,
        public ?array $metadata = null,
        public ?ObservationLevel $level = null,
        public ?string $statusMessage = null,
        ?string $parentObservationId = null,
        public ?string $version = null,
        public ?string $environment = null,
        public ?ObservationType $type = null,
    ) {
        $this->id = IdGenerator::normalizeObservationId($id);
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
        return $this->copy(traceId: $traceId, parentObservationId: $parentObservationId);
    }

    /**
     * Returns a copy stamped with the given environment when this body has none.
     */
    public function withEnvironment(?string $environment): self
    {
        if ($environment === null || $this->environment !== null) {
            return $this;
        }

        return $this->copy(environment: $environment);
    }

    /**
     * Returns a copy stamped with the given start time when this body has none.
     */
    public function startedAt(string $startTime): self
    {
        if ($this->startTime !== null) {
            return $this;
        }

        return $this->copy(startTime: $startTime);
    }

    /**
     * Returns the finished observation: what `end()` hands to the batcher.
     */
    public function completed(
        string $endTime,
        mixed $output = null,
        ?string $statusMessage = null,
        ?ObservationLevel $level = null,
    ): self {
        return $this->copy(
            endTime: $endTime,
            output: $output ?? $this->output,
            statusMessage: $statusMessage ?? $this->statusMessage,
            level: $level ?? $this->level,
        );
    }

    private function copy(
        ?string $traceId = null,
        ?string $parentObservationId = null,
        ?string $environment = null,
        ?string $startTime = null,
        ?string $endTime = null,
        mixed $output = null,
        ?string $statusMessage = null,
        ?ObservationLevel $level = null,
    ): self {
        return new self(
            id: $this->id,
            traceId: $traceId ?? $this->traceId,
            name: $this->name,
            startTime: $startTime ?? $this->startTime,
            endTime: $endTime ?? $this->endTime,
            input: $this->input,
            output: $output ?? $this->output,
            metadata: $this->metadata,
            level: $level ?? $this->level,
            statusMessage: $statusMessage ?? $this->statusMessage,
            parentObservationId: $parentObservationId ?? $this->parentObservationId,
            version: $this->version,
            environment: $environment ?? $this->environment,
            type: $this->type,
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
            'endTime' => $this->endTime,
            'input' => $this->input,
            'output' => $this->output,
            'metadata' => $this->metadata,
            'level' => $this->level?->value,
            'statusMessage' => $this->statusMessage,
            'parentObservationId' => $this->parentObservationId,
            'version' => $this->version,
            'environment' => $this->environment,
            'type' => $this->type?->value,
        ], fn(mixed $value): bool => $value !== null);
    }
}
