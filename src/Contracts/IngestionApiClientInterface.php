<?php

declare(strict_types=1);

namespace Langfuse\Contracts;

use Langfuse\Dto\IngestionBatch;
use Langfuse\Dto\IngestionResponse;

interface IngestionApiClientInterface
{
    public function send(IngestionBatch $batch): ?IngestionResponse;
}
