<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Objects;

use Axyr\Langfuse\Concerns\CreatesIngestionEvents;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Enums\EventType;
use Axyr\Langfuse\Enums\ObservationLevel;

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
