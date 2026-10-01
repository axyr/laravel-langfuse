<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Batch;

use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Tracing\CompletedObservation;

class NullEventBatcher implements EventBatcherInterface
{
    public function enqueue(CompletedObservation $observation): void {}

    public function enqueueScore(ScoreBody $score, ?string $timestamp = null): void {}

    public function flush(): void {}

    public function count(): int
    {
        return 0;
    }
}
