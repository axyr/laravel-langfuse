<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\CreateDatasetBody;
use Axyr\Langfuse\Dto\CreateDatasetItemBody;
use Axyr\Langfuse\Dto\CreateDatasetRunItemBody;
use Axyr\Langfuse\Dto\CreatePromptBody;
use Axyr\Langfuse\Dto\DatasetItemListResponse;
use Axyr\Langfuse\Dto\DatasetItemQuery;
use Axyr\Langfuse\Dto\DatasetItemResponse;
use Axyr\Langfuse\Dto\DatasetListResponse;
use Axyr\Langfuse\Dto\DatasetResponse;
use Axyr\Langfuse\Dto\DatasetRunItemListResponse;
use Axyr\Langfuse\Dto\DatasetRunItemResponse;
use Axyr\Langfuse\Dto\DatasetRunListResponse;
use Axyr\Langfuse\Dto\DatasetRunWithItemsResponse;
use Axyr\Langfuse\Dto\MetricQuery;
use Axyr\Langfuse\Dto\MetricsResponse;
use Axyr\Langfuse\Dto\ObservationListResponse;
use Axyr\Langfuse\Dto\ObservationQuery;
use Axyr\Langfuse\Dto\ObservationResponse;
use Axyr\Langfuse\Dto\PromptListResponse;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\ScoreListResponse;
use Axyr\Langfuse\Dto\ScoreQuery;
use Axyr\Langfuse\Dto\ScoreResponse;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Objects\LangfuseTrace;

interface LangfuseClientInterface
{
    public function trace(TraceBody $body): LangfuseTrace;

    public function currentTrace(): LangfuseTrace;

    public function setCurrentTrace(LangfuseTrace $trace): void;

    public function score(ScoreBody $body): void;

    public function getScore(string $scoreId): ?ScoreResponse;

    public function getScores(?ScoreQuery $query = null): ?ScoreListResponse;

    public function deleteScore(string $scoreId): bool;

    public function getObservation(string $observationId): ?ObservationResponse;

    public function getObservations(?ObservationQuery $query = null): ?ObservationListResponse;

    public function queryMetrics(MetricQuery $query): ?MetricsResponse;

    public function getDataset(string $datasetName): ?DatasetResponse;

    public function listDatasets(?int $page = null, ?int $limit = null): ?DatasetListResponse;

    public function createDataset(CreateDatasetBody $body): ?DatasetResponse;

    public function getDatasetItem(string $id): ?DatasetItemResponse;

    public function listDatasetItems(?DatasetItemQuery $query = null): ?DatasetItemListResponse;

    public function createDatasetItem(CreateDatasetItemBody $body): ?DatasetItemResponse;

    public function deleteDatasetItem(string $id): bool;

    public function getDatasetRun(string $datasetName, string $runName): ?DatasetRunWithItemsResponse;

    public function listDatasetRuns(string $datasetName, ?int $page = null, ?int $limit = null): ?DatasetRunListResponse;

    public function deleteDatasetRun(string $datasetName, string $runName): bool;

    public function createDatasetRunItem(CreateDatasetRunItemBody $body): ?DatasetRunItemResponse;

    public function listDatasetRunItems(string $datasetId, string $runName, ?int $page = null, ?int $limit = null): ?DatasetRunItemListResponse;

    public function flush(): void;

    public function isEnabled(): bool;

    /**
     * @param string|array<int, array<string, string>>|null $fallback
     */
    public function prompt(
        string $name,
        ?int $version = null,
        ?string $label = null,
        string|array|null $fallback = null,
    ): PromptInterface;

    public function createPrompt(CreatePromptBody $body): ?PromptInterface;

    public function listPrompts(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?PromptListResponse;
}
