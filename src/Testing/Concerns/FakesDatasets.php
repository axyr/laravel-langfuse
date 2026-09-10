<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Testing\Concerns;

use Axyr\Langfuse\Dto\CreateDatasetBody;
use Axyr\Langfuse\Dto\CreateDatasetItemBody;
use Axyr\Langfuse\Dto\DatasetItemListResponse;
use Axyr\Langfuse\Dto\DatasetItemQuery;
use Axyr\Langfuse\Dto\DatasetItemResponse;
use Axyr\Langfuse\Dto\DatasetListResponse;
use Axyr\Langfuse\Dto\DatasetResponse;
use Axyr\Langfuse\Dto\PromptListMeta;
use PHPUnit\Framework\Assert;

/**
 * Datasets and dataset items are unchanged in v4 and keep page-style meta.
 *
 * The signatures mirror LangfuseClientInterface, so filter arguments a seeded
 * fake ignores are kept.
 *
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 */
trait FakesDatasets
{
    /** @var array<string, DatasetResponse> */
    private array $datasetResponsesByName = [];

    /** @var array<CreateDatasetBody> */
    private array $createdDatasets = [];

    /** @var array<string, DatasetItemResponse> */
    private array $datasetItemResponsesById = [];

    /** @var array<CreateDatasetItemBody> */
    private array $createdDatasetItems = [];

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

    private function fakeListMeta(int $count, ?int $page, ?int $limit): PromptListMeta
    {
        return new PromptListMeta(
            totalItems: $count,
            totalPages: $count === 0 ? 0 : 1,
            page: $page ?? 1,
            limit: $limit ?? 10,
        );
    }
}
