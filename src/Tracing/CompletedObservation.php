<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Tracing;

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\ObservationLevel;
use Axyr\Langfuse\Enums\ObservationType;

/**
 * One finished observation waiting to be exported, together with the live trace
 * it belongs to. v4 is append-only: an observation is assembled in memory and
 * handed over exactly once, when it ends.
 */
class CompletedObservation
{
    private function __construct(
        public readonly TraceBody|SpanBody|GenerationBody|EventBody $body,
        public readonly TraceContext $context,
        public readonly string $startTime,
        public readonly string $endTime,
    ) {}

    public static function root(TraceContext $context, string $endTime): self
    {
        return new self($context->body(), $context, $context->body()->timestamp, $endTime);
    }

    public static function span(SpanBody $body, TraceContext $context): self
    {
        $startTime = $body->startTime ?? IdGenerator::timestamp();

        return new self($body, $context, $startTime, $body->endTime ?? $startTime);
    }

    public static function generation(GenerationBody $body, TraceContext $context): self
    {
        $startTime = $body->startTime ?? IdGenerator::timestamp();

        return new self($body, $context, $startTime, $body->endTime ?? $startTime);
    }

    /**
     * Events are complete at creation, so both edges are the start time.
     */
    public static function event(EventBody $body, TraceContext $context): self
    {
        return new self($body, $context, $body->startTime, $body->startTime);
    }

    public function isRoot(): bool
    {
        return $this->body instanceof TraceBody;
    }

    public function traceId(): string
    {
        return $this->context->traceId();
    }

    public function observationId(): string
    {
        return $this->isRoot() ? $this->context->rootObservationId() : $this->body->id;
    }

    public function parentId(): ?string
    {
        if ($this->body instanceof TraceBody) {
            return null;
        }

        return $this->body->parentObservationId;
    }

    public function name(): string
    {
        return $this->body->name ?? $this->type()->value;
    }

    public function level(): ?ObservationLevel
    {
        return $this->body instanceof TraceBody ? null : $this->body->level;
    }

    public function statusMessage(): ?string
    {
        return $this->body instanceof TraceBody ? null : $this->body->statusMessage;
    }

    public function type(): ObservationType
    {
        return match (true) {
            $this->body instanceof GenerationBody => ObservationType::Generation,
            $this->body instanceof EventBody => ObservationType::Event,
            $this->body instanceof SpanBody => $this->body->type ?? ObservationType::Span,
            default => ObservationType::Span,
        };
    }
}
