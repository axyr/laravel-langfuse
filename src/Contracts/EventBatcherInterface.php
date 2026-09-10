<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Tracing\CompletedObservation;

interface EventBatcherInterface
{
    /**
     * Queues one finished observation for the OTLP endpoint.
     */
    public function enqueue(CompletedObservation $observation): void;

    /**
     * Queues one score for the ingestion endpoint. `$timestamp` sets the event
     * envelope timestamp, which is the lever for overwriting a score.
     */
    public function enqueueScore(ScoreBody $score, ?string $timestamp = null): void;

    public function flush(): void;

    /**
     * Queued observations and scores waiting to be sent.
     */
    public function count(): int;
}
