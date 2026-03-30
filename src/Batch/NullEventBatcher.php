<?php

declare(strict_types=1);

namespace Langfuse\Batch;

use Langfuse\Contracts\EventBatcherInterface;
use Langfuse\Dto\IngestionEvent;

class NullEventBatcher implements EventBatcherInterface
{
    public function enqueue(IngestionEvent $event): void {}

    public function flush(): void {}

    public function count(): int
    {
        return 0;
    }
}
