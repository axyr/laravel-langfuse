<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

interface ScoreApiClientInterface
{
    public function delete(string $scoreId): bool;
}
