<?php

declare(strict_types=1);

namespace Langfuse\Dto;

use Langfuse\Contracts\SerializableInterface;
use Langfuse\Enums\ScoreDataType;

readonly class ScoreBody implements SerializableInterface
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $traceId = null,
        public ?float $value = null,
        public ?string $stringValue = null,
        public ?ScoreDataType $dataType = null,
        public ?string $observationId = null,
        public ?string $comment = null,
        public ?string $configId = null,
    ) {}

    public function withTraceId(string $traceId): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            traceId: $traceId,
            value: $this->value,
            stringValue: $this->stringValue,
            dataType: $this->dataType,
            observationId: $this->observationId,
            comment: $this->comment,
            configId: $this->configId,
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
        ], fn(mixed $value): bool => $value !== null);
    }
}
