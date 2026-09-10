<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Testing;

use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Enums\ObservationType;
use Axyr\Langfuse\Tracing\CompletedObservation;

/**
 * Records what a flush would send: completed observations for the OTLP endpoint
 * and scores for the ingestion endpoint. Nothing leaves the process.
 */
class RecordingEventBatcher implements EventBatcherInterface
{
    /** @var array<int, CompletedObservation> */
    private array $observations = [];

    /** @var array<int, ScoreBody> */
    private array $scores = [];

    public function enqueue(CompletedObservation $observation): void
    {
        $this->observations[] = $observation;
    }

    public function enqueueScore(ScoreBody $score, ?string $timestamp = null): void
    {
        $this->scores[] = $score;
    }

    public function flush(): void {}

    public function count(): int
    {
        return count($this->observations) + count($this->scores);
    }

    /**
     * @return array<int, CompletedObservation>
     */
    public function observations(): array
    {
        return $this->observations;
    }

    /**
     * @return array<int, CompletedObservation>
     */
    public function observationsOfType(ObservationType $type): array
    {
        return array_values(array_filter(
            $this->observations,
            fn(CompletedObservation $observation): bool => $observation->type() === $type,
        ));
    }

    /**
     * @return array<int, ScoreBody>
     */
    public function scores(): array
    {
        return $this->scores;
    }

    public function reset(): void
    {
        $this->observations = [];
        $this->scores = [];
    }
}
