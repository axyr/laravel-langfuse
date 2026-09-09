<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Batch;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Contracts\IngestionApiClientInterface;
use Axyr\Langfuse\Dto\IngestionBatch;
use Axyr\Langfuse\Dto\IngestionEvent;
use Illuminate\Support\Facades\Log;

class EventBatcher implements EventBatcherInterface
{
    /** @var array<IngestionEvent> */
    private array $queue = [];

    public function __construct(
        private readonly IngestionApiClientInterface $apiClient,
        private readonly LangfuseConfig $config,
    ) {}

    public function enqueue(IngestionEvent $event): void
    {
        $this->queue[] = $event;

        if (count($this->queue) >= $this->config->flushAt) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->queue === []) {
            return;
        }

        $events = $this->queue;
        $this->queue = [];

        try {
            $this->apiClient->send(new IngestionBatch(
                batch: $events,
                metadata: $this->config->batchMetadata(count($events)),
            ));
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse flush error', ['message' => $throwable->getMessage()]);
        }
    }

    public function count(): int
    {
        return count($this->queue);
    }
}
