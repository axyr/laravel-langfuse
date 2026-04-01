<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\IngestionBatch;
use Axyr\Langfuse\Dto\IngestionResponse;

interface IngestionApiClientInterface
{
    public function send(IngestionBatch $batch): ?IngestionResponse;
}
