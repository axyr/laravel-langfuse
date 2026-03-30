<?php

declare(strict_types=1);

namespace Langfuse\Objects;

use Langfuse\Concerns\CreatesIngestionEvents;
use Langfuse\Contracts\EventBatcherInterface;
use Langfuse\Dto\EventBody;
use Langfuse\Dto\GenerationBody;
use Langfuse\Dto\ScoreBody;
use Langfuse\Dto\SpanBody;
use Langfuse\Dto\TraceBody;
use Langfuse\Enums\EventType;

class LangfuseTrace
{
    use CreatesIngestionEvents;
    public function __construct(
        private readonly TraceBody $body,
        private readonly EventBatcherInterface $batcher,
    ) {
        $this->batcher->enqueue($this->createIngestionEvent(
            type: EventType::TraceCreate,
            body: $this->body,
        ));
    }

    public function getId(): string
    {
        return $this->body->id;
    }

    public function span(SpanBody $span): LangfuseSpan
    {
        return new LangfuseSpan(
            body: $span->withTraceId($this->body->id),
            batcher: $this->batcher,
        );
    }

    public function generation(GenerationBody $generation): LangfuseGeneration
    {
        return new LangfuseGeneration(
            body: $generation->withTraceId($this->body->id),
            batcher: $this->batcher,
        );
    }

    public function event(EventBody $event): void
    {
        $this->batcher->enqueue($this->createIngestionEvent(
            type: EventType::EventCreate,
            body: $event->withTraceId($this->body->id),
        ));
    }

    public function score(ScoreBody $score): void
    {
        $this->batcher->enqueue($this->createIngestionEvent(
            type: EventType::ScoreCreate,
            body: $score->withTraceId($this->body->id),
        ));
    }

}
