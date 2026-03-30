<?php

declare(strict_types=1);

namespace Langfuse\Dto;

use Langfuse\Contracts\SerializableInterface;
use Langfuse\Enums\EventType;

readonly class IngestionEvent implements SerializableInterface
{
    public function __construct(
        public string $id,
        public EventType $type,
        public string $timestamp,
        public SerializableInterface $body,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'timestamp' => $this->timestamp,
            'body' => $this->body->toArray(),
        ];
    }
}
