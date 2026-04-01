<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Objects;

use Axyr\Langfuse\Concerns\CreatesIngestionEvents;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\EventType;

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

    public function update(TraceBody $body): void
    {
        $this->batcher->enqueue($this->createIngestionEvent(
            type: EventType::TraceCreate,
            body: new TraceBody(
                id: $this->body->id,
                name: $body->name,
                userId: $body->userId,
                sessionId: $body->sessionId,
                release: $body->release,
                version: $body->version,
                input: $body->input,
                output: $body->output,
                metadata: $body->metadata,
                tags: $body->tags,
                public: $body->public,
                environment: $body->environment,
            ),
        ));
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
