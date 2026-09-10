<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Batch;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Dto\IngestionBatch;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\Otlp\OtlpExportRequest;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Otlp\OtlpRequestFactory;
use Axyr\Langfuse\Tracing\CompletedObservation;
use Illuminate\Support\Facades\Log;

/**
 * Queue handling shared by the synchronous and the queued batcher. Observations
 * go to the OTLP endpoint, scores to the ingestion endpoint, and `flushAt`
 * counts both.
 */
abstract class AbstractEventBatcher implements EventBatcherInterface
{
    /** @var array<int, CompletedObservation> */
    private array $observations = [];

    /** @var array<int, IngestionEvent> */
    private array $scores = [];

    public function __construct(
        protected readonly LangfuseConfig $config,
        protected readonly OtlpRequestFactory $requestFactory,
        protected readonly ScoreBatchFactory $scoreBatchFactory,
    ) {}

    public function enqueue(CompletedObservation $observation): void
    {
        $this->observations[] = $observation;

        $this->flushWhenFull();
    }

    public function enqueueScore(ScoreBody $score, ?string $timestamp = null): void
    {
        $this->scores[] = $this->scoreBatchFactory->event($score, $timestamp);

        $this->flushWhenFull();
    }

    public function count(): int
    {
        return count($this->observations) + count($this->scores);
    }

    public function flush(): void
    {
        if ($this->count() === 0) {
            return;
        }

        $observations = $this->observations;
        $scores = $this->scores;

        $this->observations = [];
        $this->scores = [];

        try {
            $this->send($observations, $scores);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse flush error', ['message' => $throwable->getMessage()]);
        }
    }

    /**
     * @param array<int, CompletedObservation> $observations
     * @param array<int, IngestionEvent> $scores
     */
    private function send(array $observations, array $scores): void
    {
        foreach ($this->requestFactory->build($observations) as $request) {
            $this->sendObservations($request);
        }

        if ($scores !== []) {
            $this->sendScores($this->scoreBatchFactory->batch($scores));
        }
    }

    private function flushWhenFull(): void
    {
        if ($this->count() >= $this->config->flushAt) {
            $this->flush();
        }
    }

    abstract protected function sendObservations(OtlpExportRequest $request): void;

    abstract protected function sendScores(IngestionBatch $batch): void;
}
