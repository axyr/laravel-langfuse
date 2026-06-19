<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Testing;

use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Contracts\PromptInterface;
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
use Axyr\Langfuse\Dto\DatasetRunResponse;
use Axyr\Langfuse\Dto\DatasetRunWithItemsResponse;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\MetricQuery;
use Axyr\Langfuse\Dto\MetricsResponse;
use Axyr\Langfuse\Dto\ObservationListMeta;
use Axyr\Langfuse\Dto\ObservationListResponse;
use Axyr\Langfuse\Dto\ObservationQuery;
use Axyr\Langfuse\Dto\ObservationResponse;
use Axyr\Langfuse\Dto\PromptListMeta;
use Axyr\Langfuse\Dto\PromptListResponse;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\ScoreListResponse;
use Axyr\Langfuse\Dto\ScoreQuery;
use Axyr\Langfuse\Dto\ScoreResponse;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use PHPUnit\Framework\Assert;

/**
 * Test double mirroring the full LangfuseClientInterface; its weighted method
 * count scales with the interface and is expected to be high.
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
class LangfuseFake implements LangfuseClientInterface
{
    private readonly RecordingEventBatcher $batcher;

    private LangfuseTrace $currentTrace;

    /** @var array<PromptInterface> */
    private array $promptResponses = [];

    /** @var array<string> */
    private array $deletedScores = [];

    /** @var array<string, ScoreResponse> */
    private array $scoreResponsesById = [];

    /** @var array<string, ObservationResponse> */
    private array $observationResponsesById = [];

    /** @var array<int, array<string, mixed>> */
    private array $metricsData = [];

    /** @var array<string, DatasetResponse> */
    private array $datasetResponsesByName = [];

    /** @var array<CreateDatasetBody> */
    private array $createdDatasets = [];

    /** @var array<string, DatasetItemResponse> */
    private array $datasetItemResponsesById = [];

    /** @var array<CreateDatasetItemBody> */
    private array $createdDatasetItems = [];

    /** @var array<string, DatasetRunWithItemsResponse> */
    private array $datasetRunsByName = [];

    /** @var array<CreateDatasetRunItemBody> */
    private array $createdDatasetRunItems = [];

    /** @var array<CreatePromptBody> */
    private array $createdPrompts = [];

    public function __construct()
    {
        $this->batcher = new RecordingEventBatcher();
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
        $event = new IngestionEvent(
            id: $body->id,
            type: \Axyr\Langfuse\Enums\EventType::ScoreCreate,
            timestamp: IdGenerator::timestamp(),
            body: $body,
        );

        $this->batcher->enqueue($event);
    }

    public function getScore(string $scoreId): ?ScoreResponse
    {
        return $this->scoreResponsesById[$scoreId] ?? null;
    }

    public function getScores(?ScoreQuery $query = null): ?ScoreListResponse
    {
        $data = array_values($this->scoreResponsesById);

        return new ScoreListResponse($data, $this->fakeListMeta(count($data), $query?->page, $query?->limit));
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

    public function getObservation(string $observationId): ?ObservationResponse
    {
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

    public function getDataset(string $datasetName): ?DatasetResponse
    {
        return $this->datasetResponsesByName[$datasetName] ?? null;
    }

    public function listDatasets(?int $page = null, ?int $limit = null): ?DatasetListResponse
    {
        $data = array_values($this->datasetResponsesByName);

        return new DatasetListResponse($data, $this->fakeListMeta(count($data), $page, $limit));
    }

    public function createDataset(CreateDatasetBody $body): ?DatasetResponse
    {
        $this->createdDatasets[] = $body;

        return DatasetResponse::fromArray($body->toArray());
    }

    public function withDataset(DatasetResponse $dataset): self
    {
        $this->datasetResponsesByName[$dataset->name] = $dataset;

        return $this;
    }

    public function assertDatasetCreated(?string $name = null): self
    {
        Assert::assertNotEmpty($this->createdDatasets, 'Expected at least one dataset to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(CreateDatasetBody $d): string => $d->name, $this->createdDatasets);
            Assert::assertContains($name, $names, "Expected a dataset named '{$name}' to be created but it was not.");
        }

        return $this;
    }

    public function getDatasetItem(string $id): ?DatasetItemResponse
    {
        return $this->datasetItemResponsesById[$id] ?? null;
    }

    public function listDatasetItems(?DatasetItemQuery $query = null): ?DatasetItemListResponse
    {
        $data = array_values($this->datasetItemResponsesById);

        return new DatasetItemListResponse($data, $this->fakeListMeta(count($data), $query?->page, $query?->limit));
    }

    public function createDatasetItem(CreateDatasetItemBody $body): ?DatasetItemResponse
    {
        $this->createdDatasetItems[] = $body;

        return DatasetItemResponse::fromArray($body->toArray());
    }

    public function deleteDatasetItem(string $id): bool
    {
        return true;
    }

    public function withDatasetItem(DatasetItemResponse $item): self
    {
        $this->datasetItemResponsesById[$item->id] = $item;

        return $this;
    }

    public function assertDatasetItemCreated(?string $datasetName = null): self
    {
        Assert::assertNotEmpty($this->createdDatasetItems, 'Expected at least one dataset item to be created, but none were.');

        if ($datasetName !== null) {
            $names = array_map(fn(CreateDatasetItemBody $i): string => $i->datasetName, $this->createdDatasetItems);
            Assert::assertContains($datasetName, $names, "Expected a dataset item for dataset '{$datasetName}' to be created but it was not.");
        }

        return $this;
    }

    public function getDatasetRun(string $datasetName, string $runName): ?DatasetRunWithItemsResponse
    {
        return $this->datasetRunsByName[$runName] ?? null;
    }

    public function listDatasetRuns(string $datasetName, ?int $page = null, ?int $limit = null): ?DatasetRunListResponse
    {
        $data = array_map(
            fn(DatasetRunWithItemsResponse $run): DatasetRunResponse => $run->run,
            array_values($this->datasetRunsByName),
        );

        return new DatasetRunListResponse($data, $this->fakeListMeta(count($data), $page, $limit));
    }

    public function deleteDatasetRun(string $datasetName, string $runName): bool
    {
        return true;
    }

    public function createDatasetRunItem(CreateDatasetRunItemBody $body): ?DatasetRunItemResponse
    {
        $this->createdDatasetRunItems[] = $body;

        return DatasetRunItemResponse::fromArray($body->toArray());
    }

    public function listDatasetRunItems(string $datasetId, string $runName, ?int $page = null, ?int $limit = null): ?DatasetRunItemListResponse
    {
        $run = $this->datasetRunsByName[$runName] ?? null;
        $data = $run !== null ? $run->datasetRunItems : [];

        return new DatasetRunItemListResponse($data, $this->fakeListMeta(count($data), $page, $limit));
    }

    public function withDatasetRun(DatasetRunWithItemsResponse $run): self
    {
        $this->datasetRunsByName[$run->run->name] = $run;

        return $this;
    }

    public function assertDatasetRunItemCreated(?string $runName = null): self
    {
        Assert::assertNotEmpty($this->createdDatasetRunItems, 'Expected at least one dataset run item to be created, but none were.');

        if ($runName !== null) {
            $names = array_map(fn(CreateDatasetRunItemBody $i): string => $i->runName, $this->createdDatasetRunItems);
            Assert::assertContains($runName, $names, "Expected a dataset run item for run '{$runName}' to be created but it was not.");
        }

        return $this;
    }

    private function fakeListMeta(int $count, ?int $page, ?int $limit): PromptListMeta
    {
        return new PromptListMeta(
            totalItems: $count,
            totalPages: $count === 0 ? 0 : 1,
            page: $page ?? 1,
            limit: $limit ?? 10,
        );
    }

    public function flush(): void
    {
        $this->batcher->flush();
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function prompt(
        string $name,
        ?int $version = null,
        ?string $label = null,
        string|array|null $fallback = null,
    ): PromptInterface {
        if (isset($this->promptResponses[$name])) {
            return $this->promptResponses[$name];
        }

        if (is_string($fallback)) {
            return \Axyr\Langfuse\Dto\PromptFactory::fallbackText($name, $fallback);
        }

        if (is_array($fallback)) {
            return \Axyr\Langfuse\Dto\PromptFactory::fallbackChat($name, $fallback);
        }

        throw \Axyr\Langfuse\Exceptions\PromptNotFoundException::forName($name);
    }

    public function createPrompt(CreatePromptBody $body): ?PromptInterface
    {
        $this->createdPrompts[] = $body;

        return \Axyr\Langfuse\Dto\PromptFactory::fromApiResponse($body->toArray());
    }

    public function listPrompts(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?PromptListResponse
    {
        return PromptListResponse::fromArray([
            'data' => [],
            'meta' => ['totalItems' => 0, 'totalPages' => 0, 'page' => $page ?? 1, 'limit' => $limit ?? 10],
        ]);
    }

    public function withPrompt(PromptInterface $prompt): self
    {
        $this->promptResponses[$prompt->getName()] = $prompt;

        return $this;
    }

    /**
     * @return array<IngestionEvent>
     */
    public function events(): array
    {
        return $this->batcher->events();
    }

    public function assertTraceCreated(?string $name = null): self
    {
        $traces = $this->batcher->eventsOfType('trace-create');

        Assert::assertNotEmpty($traces, 'Expected at least one trace to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $traces);
            Assert::assertContains($name, $names, "Expected a trace named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertGenerationCreated(?string $name = null): self
    {
        $generations = $this->batcher->eventsOfType('generation-create');

        Assert::assertNotEmpty($generations, 'Expected at least one generation to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $generations);
            Assert::assertContains($name, $names, "Expected a generation named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertScoreCreated(?string $name = null): self
    {
        $scores = $this->batcher->eventsOfType('score-create');

        Assert::assertNotEmpty($scores, 'Expected at least one score to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $scores);
            Assert::assertContains($name, $names, "Expected a score named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertSpanCreated(?string $name = null): self
    {
        $spans = $this->batcher->eventsOfType('span-create');

        Assert::assertNotEmpty($spans, 'Expected at least one span to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $spans);
            Assert::assertContains($name, $names, "Expected a span named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertEventCreated(?string $name = null): self
    {
        $events = $this->batcher->eventsOfType('event-create');

        Assert::assertNotEmpty($events, 'Expected at least one event to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $events);
            Assert::assertContains($name, $names, "Expected an event named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertNothingSent(): self
    {
        Assert::assertEmpty(
            $this->batcher->events(),
            'Expected no events to be sent, but ' . count($this->batcher->events()) . ' were recorded.',
        );

        return $this;
    }

    public function assertEventCount(int $expected): self
    {
        Assert::assertCount(
            $expected,
            $this->batcher->events(),
            'Expected ' . $expected . ' events but found ' . count($this->batcher->events()) . '.',
        );

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

    public function assertPromptCreated(?string $name = null): self
    {
        Assert::assertNotEmpty($this->createdPrompts, 'Expected at least one prompt to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(CreatePromptBody $p): string => $p->name, $this->createdPrompts);
            Assert::assertContains($name, $names, "Expected a prompt named '{$name}' to be created but it was not.");
        }

        return $this;
    }
}
