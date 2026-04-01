<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\IngestionEvent;

interface EventBatcherInterface
{
    public function enqueue(IngestionEvent $event): void;

    public function flush(): void;

    public function count(): int;
}
