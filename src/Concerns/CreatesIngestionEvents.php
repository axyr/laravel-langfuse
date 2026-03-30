<?php

declare(strict_types=1);

namespace Langfuse\Concerns;

use Illuminate\Support\Str;
use Langfuse\Contracts\SerializableInterface;
use Langfuse\Dto\IngestionEvent;
use Langfuse\Enums\EventType;

trait CreatesIngestionEvents
{
    protected function generateId(): string
    {
        return (string) Str::uuid();
    }

    protected function generateTimestamp(): string
    {
        return now()->toIso8601ZuluString();
    }

    protected function createIngestionEvent(EventType $type, SerializableInterface $body): IngestionEvent
    {
        return new IngestionEvent(
            id: $this->generateId(),
            type: $type,
            timestamp: $this->generateTimestamp(),
            body: $body,
        );
    }
}
