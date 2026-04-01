<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Batch;

use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Dto\IngestionEvent;

class NullEventBatcher implements EventBatcherInterface
{
    public function enqueue(IngestionEvent $event): void {}

    public function flush(): void {}

    public function count(): int
    {
        return 0;
    }
}
