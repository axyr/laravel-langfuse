<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\CreateDatasetBody;
use Axyr\Langfuse\Dto\DatasetListResponse;
use Axyr\Langfuse\Dto\DatasetResponse;

interface DatasetApiClientInterface
{
    public function get(string $datasetName): ?DatasetResponse;

    public function list(?int $page = null, ?int $limit = null): ?DatasetListResponse;

    public function create(CreateDatasetBody $body): ?DatasetResponse;
}
