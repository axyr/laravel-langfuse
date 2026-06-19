<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\CreateDatasetItemBody;
use Axyr\Langfuse\Dto\DatasetItemListResponse;
use Axyr\Langfuse\Dto\DatasetItemQuery;
use Axyr\Langfuse\Dto\DatasetItemResponse;

interface DatasetItemApiClientInterface
{
    public function get(string $id): ?DatasetItemResponse;

    public function list(?DatasetItemQuery $query = null): ?DatasetItemListResponse;

    public function create(CreateDatasetItemBody $body): ?DatasetItemResponse;

    public function delete(string $id): bool;
}
