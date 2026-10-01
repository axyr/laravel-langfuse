<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Testing\Concerns;

use Axyr\Langfuse\Dto\CursorMeta;
use Axyr\Langfuse\Dto\MetricQuery;
use Axyr\Langfuse\Dto\MetricsResponse;
use Axyr\Langfuse\Dto\ObservationListMeta;
use Axyr\Langfuse\Dto\ObservationListResponse;
use Axyr\Langfuse\Dto\ObservationQuery;
use Axyr\Langfuse\Dto\ObservationResponse;
use Axyr\Langfuse\Dto\ScoreListResponse;
use Axyr\Langfuse\Dto\ScoreQuery;
use Axyr\Langfuse\Dto\ScoreResponse;
use PHPUnit\Framework\Assert;

/**
 * Seeded score, observation and metrics reads.
 *
 * The signatures mirror LangfuseClientInterface, so filter arguments a seeded
 * fake ignores are kept.
 *
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 */
trait FakesReads
{
    /** @var array<string> */
    private array $deletedScores = [];

    /** @var array<string, ScoreResponse> */
    private array $scoreResponsesById = [];

    /** @var array<string, ObservationResponse> */
    private array $observationResponsesById = [];

    /** @var array<int, array<string, mixed>> */
    private array $metricsData = [];

    public function getScore(string $scoreId): ?ScoreResponse
    {
        return $this->scoreResponsesById[$scoreId] ?? null;
    }

    public function getScores(?ScoreQuery $query = null): ?ScoreListResponse
    {
        $data = array_values($this->scoreResponsesById);

        return new ScoreListResponse($data, new CursorMeta(limit: $query->limit ?? 50));
    }

    public function deleteScore(string $scoreId): bool
    {
        $this->deletedScores[] = $scoreId;

        return true;
    }

    public function withScore(ScoreResponse $score): self
    {
        $this->scoreResponsesById[$score->id] = $score;

        return $this;
    }

    public function assertScoreDeleted(?string $scoreId = null): self
    {
        Assert::assertNotEmpty($this->deletedScores, 'Expected at least one score to be deleted, but none were.');

        if ($scoreId !== null) {
            Assert::assertContains($scoreId, $this->deletedScores, "Expected score '{$scoreId}' to be deleted but it was not.");
        }

        return $this;
    }

    public function getObservation(
        string $observationId,
        ?string $fromStartTime = null,
        ?string $toStartTime = null,
        ?string $fields = null,
    ): ?ObservationResponse {
        return $this->observationResponsesById[$observationId] ?? null;
    }

    public function getObservations(?ObservationQuery $query = null): ?ObservationListResponse
    {
        return new ObservationListResponse(
            data: array_values($this->observationResponsesById),
            meta: new ObservationListMeta(),
        );
    }

    public function withObservation(ObservationResponse $observation): self
    {
        $this->observationResponsesById[$observation->id] = $observation;

        return $this;
    }

    public function queryMetrics(MetricQuery $query): ?MetricsResponse
    {
        return new MetricsResponse(data: $this->metricsData);
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     */
    public function withMetrics(array $data): self
    {
        $this->metricsData = $data;

        return $this;
    }
}
