<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Objects;

use Axyr\Langfuse\Contracts\EndsOnShutdownInterface;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Tracing\CompletedObservation;
use Axyr\Langfuse\Tracing\TraceContext;
use Illuminate\Support\Facades\Log;

/**
 * The root observation of a trace. Nothing is sent when the trace is created:
 * v4 is append-only, so the root span is assembled in memory and exported once,
 * when `end()` runs or the application terminates.
 */
class LangfuseTrace implements EndsOnShutdownInterface
{
    protected TraceContext $context;

    protected EventBatcherInterface $batcher;

    protected ?OpenObservationRegistry $registry;

    protected bool $ended = false;

    public function __construct(
        TraceBody $body,
        EventBatcherInterface $batcher,
        ?OpenObservationRegistry $registry = null,
    ) {
        $this->context = new TraceContext($body);
        $this->batcher = $batcher;
        $this->registry = $registry;

        $this->registry?->register($this);
    }

    public function getId(): string
    {
        return $this->context->traceId();
    }

    /**
     * The 16 hex id of the trace's root span, which is what experiment items and
     * trace-level scores point at.
     */
    public function getRootObservationId(): string
    {
        return $this->context->rootObservationId();
    }

    public function getBody(): TraceBody
    {
        return $this->context->body();
    }

    public function getContext(): TraceContext
    {
        return $this->context;
    }

    public function hasEnded(): bool
    {
        return $this->ended;
    }

    /**
     * Folds the given fields into the in-memory trace. Nothing is sent here; the
     * values reach Langfuse when the batch is serialised.
     */
    public function update(TraceBody $body): void
    {
        if ($this->ended) {
            Log::warning('Langfuse trace updated after it ended', ['traceId' => $this->getId()]);

            return;
        }

        $this->context->merge($body);
    }

    public function span(SpanBody $span): LangfuseSpan
    {
        return new LangfuseSpan(
            body: $span
                ->withContext($this->context->traceId(), $this->parentFor($span->parentObservationId))
                ->withEnvironment($this->context->environment()),
            batcher: $this->batcher,
            context: $this->context,
            registry: $this->registry,
        );
    }

    public function generation(GenerationBody $generation): LangfuseGeneration
    {
        return new LangfuseGeneration(
            body: $generation
                ->withContext($this->context->traceId(), $this->parentFor($generation->parentObservationId))
                ->withEnvironment($this->context->environment()),
            batcher: $this->batcher,
            context: $this->context,
            registry: $this->registry,
        );
    }

    public function event(EventBody $event): void
    {
        $body = $event
            ->withContext($this->context->traceId(), $this->parentFor($event->parentObservationId))
            ->withEnvironment($this->context->environment());

        $this->batcher->enqueue(CompletedObservation::event($body, $this->context));
    }

    public function score(ScoreBody $score, ?string $timestamp = null): void
    {
        $this->batcher->enqueueScore(
            $score->withTraceId($this->getId())->withEnvironment($this->context->environment()),
            $timestamp,
        );
    }

    /**
     * Exports the root span. A second call is ignored.
     */
    public function end(?string $endTime = null, mixed $output = null): void
    {
        if ($this->ended) {
            Log::warning('Langfuse trace ended twice', ['traceId' => $this->getId()]);

            return;
        }

        if ($output !== null) {
            $this->context->merge(new TraceBody(id: $this->getId(), output: $output));
        }

        $this->ended = true;
        $this->registry?->unregister($this);

        $this->batcher->enqueue(CompletedObservation::root(
            $this->context,
            $endTime ?? IdGenerator::timestamp(),
        ));
    }

    public function endOnShutdown(): void
    {
        $this->end();
    }

    /**
     * Children nest under the root observation unless they chose their own parent.
     */
    private function parentFor(?string $parentObservationId): string
    {
        return $parentObservationId ?? $this->context->rootObservationId();
    }
}
