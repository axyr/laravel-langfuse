<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\MetricQuery;
use Axyr\Langfuse\Dto\MetricsResponse;

interface MetricsApiClientInterface
{
    public function query(MetricQuery $query): ?MetricsResponse;
}
