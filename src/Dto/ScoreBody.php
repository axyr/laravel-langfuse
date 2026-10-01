<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;
use Axyr\Langfuse\Enums\ScoreDataType;

/**
 * Scores keep their free-form UUID id: they are not OTLP spans. `traceId` and
 * `observationId` are normalised the same way the observation ids are, so a
 * score written against a custom id still lands on the observation produced
 * from that id.
 *
 * The v4 write schema has no `stringValue`: string scores go into the
 * polymorphic `value`. `stringValue` is kept as a constructor argument for
 * backwards compatibility and is mapped into `value` on the wire.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
readonly class ScoreBody implements SerializableInterface
{
    public string $id;

    public ?string $traceId;

    public ?string $observationId;

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public string $name,
        ?string $id = null,
        ?string $traceId = null,
        public float|bool|string|null $value = null,
        public ?string $stringValue = null,
        public ?ScoreDataType $dataType = null,
        ?string $observationId = null,
        public ?string $comment = null,
        public ?string $configId = null,
        public ?string $sessionId = null,
        public ?string $environment = null,
        public ?string $datasetRunId = null,
        public ?array $metadata = null,
        public ?string $queueId = null,
    ) {
        $this->id = $id ?? IdGenerator::uuid();
        $this->traceId = $traceId === null ? null : IdGenerator::normalizeTraceId($traceId);
        $this->observationId = $observationId === null
            ? null
            : IdGenerator::normalizeObservationId($observationId);
    }

    public function withTraceId(string $traceId): self
    {
        return $this->copy($traceId, $this->environment);
    }

    /**
     * Returns a copy stamped with the given environment when this body has none.
     */
    public function withEnvironment(?string $environment): self
    {
        if ($environment === null || $this->environment !== null) {
            return $this;
        }

        return $this->copy($this->traceId, $environment);
    }

    private function copy(?string $traceId, ?string $environment): self
    {
        return new self(
            name: $this->name,
            id: $this->id,
            traceId: $traceId,
            value: $this->value,
            stringValue: $this->stringValue,
            dataType: $this->dataType,
            observationId: $this->observationId,
            comment: $this->comment,
            configId: $this->configId,
            sessionId: $this->sessionId,
            environment: $environment,
            datasetRunId: $this->datasetRunId,
            metadata: $this->metadata,
            queueId: $this->queueId,
        );
    }

    /**
     * BOOLEAN scores are numeric on the wire (1/0); string values land in `value`.
     */
    private function wireValue(): float|int|string|null
    {
        $value = $this->value ?? $this->stringValue;

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
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
            'value' => $this->wireValue(),
            'dataType' => $this->dataType?->value,
            'observationId' => $this->observationId,
            'comment' => $this->comment,
            'configId' => $this->configId,
            'sessionId' => $this->sessionId,
            'environment' => $this->environment,
            'datasetRunId' => $this->datasetRunId,
            'metadata' => $this->metadata,
            'queueId' => $this->queueId,
        ], fn(mixed $value): bool => $value !== null);
    }
}
