<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Concerns;

use Axyr\Langfuse\Contracts\SerializableInterface;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Enums\EventType;

trait CreatesIngestionEvents
{
    protected function generateId(): string
    {
        return IdGenerator::uuid();
    }

    protected function generateTimestamp(): string
    {
        return IdGenerator::timestamp();
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
