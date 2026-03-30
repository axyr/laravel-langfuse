<?php

declare(strict_types=1);

namespace Langfuse\Objects;

use Langfuse\Concerns\CreatesIngestionEvents;
use Langfuse\Contracts\EventBatcherInterface;
use Langfuse\Dto\GenerationBody;
use Langfuse\Dto\Usage;
use Langfuse\Enums\EventType;
use Langfuse\Enums\ObservationLevel;

class LangfuseGeneration
{
    use CreatesIngestionEvents;
    public function __construct(
        private readonly GenerationBody $body,
        private readonly EventBatcherInterface $batcher,
    ) {
        $this->batcher->enqueue($this->createIngestionEvent(
            type: EventType::GenerationCreate,
            body: $this->body,
        ));
    }

    public function getId(): string
    {
        return $this->body->id;
    }

    public function getTraceId(): ?string
    {
        return $this->body->traceId;
    }

    public function end(
        ?string $endTime = null,
        mixed $output = null,
        ?Usage $usage = null,
        ?string $statusMessage = null,
        ?ObservationLevel $level = null,
    ): void {
        $this->batcher->enqueue($this->createIngestionEvent(
            type: EventType::GenerationUpdate,
            body: new GenerationBody(
                id: $this->body->id,
                traceId: $this->body->traceId,
                endTime: $endTime ?? $this->generateTimestamp(),
                output: $output,
                usage: $usage,
                statusMessage: $statusMessage,
                level: $level,
            ),
        ));
    }

}
