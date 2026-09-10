<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\ExperimentItemListResponse;
use Axyr\Langfuse\Dto\ExperimentItemQuery;
use Axyr\Langfuse\Dto\ExperimentListResponse;
use Axyr\Langfuse\Dto\ExperimentQuery;
use Axyr\Langfuse\Dto\ExperimentResponse;

interface ExperimentApiClientInterface
{
    public function listExperiments(ExperimentQuery $query): ?ExperimentListResponse;

    public function listExperimentItems(ExperimentItemQuery $query): ?ExperimentItemListResponse;

    public function getExperiment(string $experimentId, string $fromStartTime, ?string $toStartTime = null): ?ExperimentResponse;
}
