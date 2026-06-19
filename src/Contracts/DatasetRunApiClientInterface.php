<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\CreateDatasetRunItemBody;
use Axyr\Langfuse\Dto\DatasetRunItemListResponse;
use Axyr\Langfuse\Dto\DatasetRunItemResponse;
use Axyr\Langfuse\Dto\DatasetRunListResponse;
use Axyr\Langfuse\Dto\DatasetRunWithItemsResponse;

interface DatasetRunApiClientInterface
{
    public function getRun(string $datasetName, string $runName): ?DatasetRunWithItemsResponse;

    public function listRuns(string $datasetName, ?int $page = null, ?int $limit = null): ?DatasetRunListResponse;

    public function deleteRun(string $datasetName, string $runName): bool;

    public function createRunItem(CreateDatasetRunItemBody $body): ?DatasetRunItemResponse;

    public function listRunItems(string $datasetId, string $runName, ?int $page = null, ?int $limit = null): ?DatasetRunItemListResponse;
}
