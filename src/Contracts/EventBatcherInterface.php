<?php

declare(strict_types=1);

namespace Langfuse\Contracts;

use Langfuse\Dto\IngestionEvent;

interface EventBatcherInterface
{
    public function enqueue(IngestionEvent $event): void;

    public function flush(): void;

    public function count(): int;
}
