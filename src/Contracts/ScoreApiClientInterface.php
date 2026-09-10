<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\IngestionBatch;
use Axyr\Langfuse\Dto\IngestionResponse;
use Axyr\Langfuse\Dto\ScoreListResponse;
use Axyr\Langfuse\Dto\ScoreQuery;
use Axyr\Langfuse\Dto\ScoreResponse;

interface ScoreApiClientInterface
{
    /**
     * Writes a batch of `score-create` events.
     */
    public function ingest(IngestionBatch $batch): ?IngestionResponse;

    public function get(string $scoreId): ?ScoreResponse;

    public function getMany(?ScoreQuery $query = null): ?ScoreListResponse;

    public function delete(string $scoreId): bool;
}
