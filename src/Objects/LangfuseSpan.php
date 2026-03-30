<?php

declare(strict_types=1);

namespace Langfuse\Objects;

use Langfuse\Concerns\CreatesIngestionEvents;
use Langfuse\Contracts\EventBatcherInterface;
use Langfuse\Dto\EventBody;
use Langfuse\Dto\GenerationBody;
use Langfuse\Dto\SpanBody;
use Langfuse\Enums\EventType;

class LangfuseSpan
{
    use CreatesIngestionEvents;
    public function __construct(
        private readonly SpanBody $body,
        private readonly EventBatcherInterface $batcher,
    ) {
        $this->batcher->enqueue($this->createIngestionEvent(
            type: EventType::SpanCreate,
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

    public function span(SpanBody $span): self
    {
        return new self(
            body: $span->withContext($this->body->traceId ?? '', $this->body->id),
            batcher: $this->batcher,
        );
    }

    public function generation(GenerationBody $generation): LangfuseGeneration
    {
        return new LangfuseGeneration(
            body: $generation->withContext($this->body->traceId ?? '', $this->body->id),
            batcher: $this->batcher,
        );
    }

    public function event(EventBody $event): void
    {
        $this->batcher->enqueue($this->createIngestionEvent(
            type: EventType::EventCreate,
            body: $event->withContext($this->body->traceId ?? '', $this->body->id),
        ));
    }

    public function end(
        ?string $endTime = null,
        mixed $output = null,
        ?string $statusMessage = null,
    ): void {
        $this->batcher->enqueue($this->createIngestionEvent(
            type: EventType::SpanUpdate,
            body: new SpanBody(
                id: $this->body->id,
                traceId: $this->body->traceId,
                endTime: $endTime ?? $this->generateTimestamp(),
                output: $output,
                statusMessage: $statusMessage,
            ),
        ));
    }

}
