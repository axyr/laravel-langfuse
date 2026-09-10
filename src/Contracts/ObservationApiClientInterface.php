<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\ObservationListResponse;
use Axyr\Langfuse\Dto\ObservationQuery;
use Axyr\Langfuse\Dto\ObservationResponse;

interface ObservationApiClientInterface
{
    public function get(
        string $observationId,
        ?string $fromStartTime = null,
        ?string $toStartTime = null,
        ?string $fields = null,
    ): ?ObservationResponse;

    public function getMany(?ObservationQuery $query = null): ?ObservationListResponse;
}
