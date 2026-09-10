<?php

declare(strict_types=1);

namespace Axyr\Langfuse;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\DatasetApiClientInterface;
use Axyr\Langfuse\Contracts\DatasetItemApiClientInterface;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Contracts\ExperimentApiClientInterface;
use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Contracts\MetricsApiClientInterface;
use Axyr\Langfuse\Contracts\ObservationApiClientInterface;
use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Contracts\PromptInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Contracts\TraceContextResolverInterface;
use Axyr\Langfuse\Dto\CreateDatasetBody;
use Axyr\Langfuse\Dto\CreateDatasetItemBody;
use Axyr\Langfuse\Dto\CreatePromptBody;
use Axyr\Langfuse\Dto\DatasetItemListResponse;
use Axyr\Langfuse\Dto\DatasetItemQuery;
use Axyr\Langfuse\Dto\DatasetItemResponse;
use Axyr\Langfuse\Dto\DatasetListResponse;
use Axyr\Langfuse\Dto\DatasetResponse;
use Axyr\Langfuse\Dto\ExperimentItemListResponse;
use Axyr\Langfuse\Dto\ExperimentItemQuery;
use Axyr\Langfuse\Dto\ExperimentListResponse;
use Axyr\Langfuse\Dto\ExperimentQuery;
use Axyr\Langfuse\Dto\ExperimentResponse;
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
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Axyr\Langfuse\Objects\OpenObservationRegistry;
use Axyr\Langfuse\Prompt\CurrentPromptRegistry;
use Axyr\Langfuse\Prompt\PromptManager;
use Axyr\Langfuse\Tracing\NullTraceContextResolver;
use Illuminate\Support\Facades\Log;

/**
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
class LangfuseClient implements LangfuseClientInterface
{
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
        private readonly ExperimentApiClientInterface $experimentApiClient,
        private readonly OpenObservationRegistry $registry = new OpenObservationRegistry(),
        private readonly CurrentPromptRegistry $promptRegistry = new CurrentPromptRegistry(),
        private readonly TraceContextResolverInterface $contextResolver = new NullTraceContextResolver(),
    ) {
        $this->currentTrace = new NullLangfuseTrace();
    }

    public function trace(TraceBody $body): LangfuseTrace
    {
        return new LangfuseTrace(
            body: $this->applyTraceContext(
                $body->withEnvironment($this->config->environment)->withRelease($this->config->release),
            ),
            batcher: $this->batcher,
            registry: $this->registry,
        );
    }

    /**
     * Fills in userId and sessionId from the context resolver when the body does
     * not set them. Resolver failures must never break the traced code path.
     */
    private function applyTraceContext(TraceBody $body): TraceBody
    {
        try {
            return $body
                ->withUserId($this->resolveDefaultUserId($body))
                ->withSessionId($this->resolveDefaultSessionId($body));
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse trace context resolution failed', ['message' => $throwable->getMessage()]);

            return $body;
        }
    }

    private function resolveDefaultUserId(TraceBody $body): ?string
    {
        if (! $this->config->userTracingEnabled || $body->userId !== null) {
            return null;
        }

        return $this->contextResolver->resolveUserId();
    }

    private function resolveDefaultSessionId(TraceBody $body): ?string
    {
        if (! $this->config->sessionTracingEnabled || $body->sessionId !== null) {
            return null;
        }

        return $this->contextResolver->resolveSessionId();
    }

    public function currentTrace(): LangfuseTrace
    {
        return $this->currentTrace;
    }

    public function setCurrentTrace(LangfuseTrace $trace): void
    {
        $this->currentTrace = $trace;
    }

    public function score(ScoreBody $body, ?string $timestamp = null): void
    {
        $this->batcher->enqueueScore($body->withEnvironment($this->config->environment), $timestamp);
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

    public function getObservation(
        string $observationId,
        ?string $fromStartTime = null,
        ?string $toStartTime = null,
        ?string $fields = null,
    ): ?ObservationResponse {
        return $this->observationApiClient->get($observationId, $fromStartTime, $toStartTime, $fields);
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

    public function listExperiments(ExperimentQuery $query): ?ExperimentListResponse
    {
        return $this->experimentApiClient->listExperiments($query);
    }

    public function listExperimentItems(ExperimentItemQuery $query): ?ExperimentItemListResponse
    {
        return $this->experimentApiClient->listExperimentItems($query);
    }

    public function getExperiment(string $experimentId, string $fromStartTime, ?string $toStartTime = null): ?ExperimentResponse
    {
        return $this->experimentApiClient->getExperiment($experimentId, $fromStartTime, $toStartTime);
    }

    public function flush(): void
    {
        $this->batcher->flush();
    }

    public function shutdown(): void
    {
        $this->registry->endAll();
        $this->batcher->flush();

        // The unit of work is over: drop the references so a long-running process
        // that shuts down repeatedly does not accumulate them.
        $this->registry->reset();
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
        $prompt = $this->promptManager->get($name, $version, $label, $fallback);

        $this->promptRegistry->set($prompt);

        return $prompt;
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
