<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Objects;

use Axyr\Langfuse\Concerns\CreatesIngestionEvents;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Enums\EventType;

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
