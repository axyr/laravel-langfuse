<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;
use Axyr\Langfuse\Enums\ScoreDataType;

readonly class ScoreBody implements SerializableInterface
{
    public string $id;

    public function __construct(
        public string $name,
        ?string $id = null,
        public ?string $traceId = null,
        public ?float $value = null,
        public ?string $stringValue = null,
        public ?ScoreDataType $dataType = null,
        public ?string $observationId = null,
        public ?string $comment = null,
        public ?string $configId = null,
        public ?string $sessionId = null,
        public ?string $environment = null,
    ) {
        $this->id = $id ?? IdGenerator::uuid();
    }

    public function withTraceId(string $traceId): self
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
            'value' => $this->value,
            'stringValue' => $this->stringValue,
            'dataType' => $this->dataType?->value,
            'observationId' => $this->observationId,
            'comment' => $this->comment,
            'configId' => $this->configId,
            'sessionId' => $this->sessionId,
            'environment' => $this->environment,
        ], fn(mixed $value): bool => $value !== null);
    }
}
