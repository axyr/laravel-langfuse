<?php

declare(strict_types=1);

namespace Axyr\Langfuse;

use Axyr\Langfuse\Concerns\CreatesIngestionEvents;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\DatasetApiClientInterface;
use Axyr\Langfuse\Contracts\DatasetItemApiClientInterface;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Contracts\MetricsApiClientInterface;
use Axyr\Langfuse\Contracts\ObservationApiClientInterface;
use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Contracts\PromptInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Dto\CreateDatasetBody;
use Axyr\Langfuse\Dto\CreateDatasetItemBody;
use Axyr\Langfuse\Dto\CreatePromptBody;
use Axyr\Langfuse\Dto\DatasetItemListResponse;
use Axyr\Langfuse\Dto\DatasetItemQuery;
use Axyr\Langfuse\Dto\DatasetItemResponse;
use Axyr\Langfuse\Dto\DatasetListResponse;
use Axyr\Langfuse\Dto\DatasetResponse;
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
use Axyr\Langfuse\Enums\EventType;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Axyr\Langfuse\Prompt\PromptManager;

class LangfuseClient implements LangfuseClientInterface
{
    use CreatesIngestionEvents;

    private LangfuseTrace $currentTrace;

    public function __construct(
        private readonly EventBatcherInterface $batcher,
        private readonly LangfuseConfig $config,
        private readonly PromptManager $promptManager,
        private readonly ScoreApiClientInterface $scoreApiClient,
        private readonly PromptApiClientInterface $promptApiClient,
        private readonly ObservationApiClientInterface $observationApiClient,
        private readonly MetricsApiClientInterface $metricsApiClient,
        private readonly DatasetApiClientInterface $datasetApiClient,
        private readonly DatasetItemApiClientInterface $datasetItemApiClient,
    ) {
        $this->currentTrace = new NullLangfuseTrace();
    }

    public function trace(TraceBody $body): LangfuseTrace
    {
        return new LangfuseTrace(
            body: $body,
            batcher: $this->batcher,
        );
    }

    public function currentTrace(): LangfuseTrace
    {
        return $this->currentTrace;
    }

    public function setCurrentTrace(LangfuseTrace $trace): void
    {
        $this->currentTrace = $trace;
    }

    public function score(ScoreBody $body): void
    {
        $this->batcher->enqueue($this->createIngestionEvent(
            type: EventType::ScoreCreate,
            body: $body,
        ));
    }

    public function getScore(string $scoreId): ?ScoreResponse
    {
        return $this->scoreApiClient->get($scoreId);
    }

    public function getScores(?ScoreQuery $query = null): ?ScoreListResponse
    {
        return $this->scoreApiClient->getMany($query);
    }

    public function deleteScore(string $scoreId): bool
    {
        return $this->scoreApiClient->delete($scoreId);
    }

    public function getObservation(string $observationId): ?ObservationResponse
    {
        return $this->observationApiClient->get($observationId);
    }

    public function getObservations(?ObservationQuery $query = null): ?ObservationListResponse
    {
        return $this->observationApiClient->getMany($query);
    }

    public function queryMetrics(MetricQuery $query): ?MetricsResponse
    {
        return $this->metricsApiClient->query($query);
    }

    public function getDataset(string $datasetName): ?DatasetResponse
    {
        return $this->datasetApiClient->get($datasetName);
    }

    public function listDatasets(?int $page = null, ?int $limit = null): ?DatasetListResponse
    {
        return $this->datasetApiClient->list($page, $limit);
    }

    public function createDataset(CreateDatasetBody $body): ?DatasetResponse
    {
        return $this->datasetApiClient->create($body);
    }

    public function getDatasetItem(string $id): ?DatasetItemResponse
    {
        return $this->datasetItemApiClient->get($id);
    }

    public function listDatasetItems(?DatasetItemQuery $query = null): ?DatasetItemListResponse
    {
        return $this->datasetItemApiClient->list($query);
    }

    public function createDatasetItem(CreateDatasetItemBody $body): ?DatasetItemResponse
    {
        return $this->datasetItemApiClient->create($body);
    }

    public function deleteDatasetItem(string $id): bool
    {
        return $this->datasetItemApiClient->delete($id);
    }

    public function flush(): void
    {
        $this->batcher->flush();
    }

    public function isEnabled(): bool
    {
        return $this->config->enabled;
    }

    public function prompt(
        string $name,
        ?int $version = null,
        ?string $label = null,
        string|array|null $fallback = null,
    ): PromptInterface {
        return $this->promptManager->get($name, $version, $label, $fallback);
    }

    public function createPrompt(CreatePromptBody $body): ?PromptInterface
    {
        return $this->promptApiClient->create($body);
    }

    public function listPrompts(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?PromptListResponse
    {
        return $this->promptApiClient->list($name, $label, $page, $limit);
    }
}
