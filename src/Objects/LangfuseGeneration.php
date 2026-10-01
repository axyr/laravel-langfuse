<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Objects;

use Axyr\Langfuse\Contracts\EndsOnShutdownInterface;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Enums\ObservationLevel;
use Axyr\Langfuse\Tracing\CompletedObservation;
use Axyr\Langfuse\Tracing\TraceContext;
use Illuminate\Support\Facades\Log;

/**
 * A generation is built in memory and exported once, by `end()`.
 */
class LangfuseGeneration implements EndsOnShutdownInterface
{
    protected GenerationBody $body;

    protected EventBatcherInterface $batcher;

    protected TraceContext $context;

    protected ?OpenObservationRegistry $registry;

    protected bool $ended = false;

    public function __construct(
        GenerationBody $body,
        EventBatcherInterface $batcher,
        TraceContext $context,
        ?OpenObservationRegistry $registry = null,
    ) {
        $this->body = $body->startedAt(IdGenerator::timestamp());
        $this->batcher = $batcher;
        $this->context = $context;
        $this->registry = $registry;

        $this->registry?->register($this);
    }

    public function getId(): string
    {
        return $this->body->id;
    }

    public function getTraceId(): ?string
    {
        return $this->body->traceId;
    }

    public function getBody(): GenerationBody
    {
        return $this->body;
    }

    public function hasEnded(): bool
    {
        return $this->ended;
    }

    public function end(
        ?string $endTime = null,
        mixed $output = null,
        ?Usage $usage = null,
        ?string $statusMessage = null,
        ?ObservationLevel $level = null,
    ): void {
        if ($this->ended) {
            Log::warning('Langfuse generation ended twice', ['observationId' => $this->getId()]);

            return;
        }

        $this->ended = true;
        $this->registry?->unregister($this);

        $this->body = $this->body->completed(
            endTime: $endTime ?? IdGenerator::timestamp(),
            output: $output,
            usage: $usage,
            statusMessage: $statusMessage,
            level: $level,
        );

        $this->batcher->enqueue(CompletedObservation::generation($this->body, $this->context));
    }

    public function endOnShutdown(): void
    {
        $this->end(statusMessage: 'ended by shutdown', level: ObservationLevel::WARNING);
    }
}
