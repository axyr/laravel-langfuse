<?php

declare(strict_types=1);

namespace Langfuse\Batch;

use Illuminate\Support\Facades\Log;
use Langfuse\Config\LangfuseConfig;
use Langfuse\Contracts\EventBatcherInterface;
use Langfuse\Contracts\IngestionApiClientInterface;
use Langfuse\Dto\IngestionBatch;
use Langfuse\Dto\IngestionEvent;
use Langfuse\Dto\IngestionResponse;

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
            $response = $this->apiClient->send(new IngestionBatch(
                batch: $events,
                metadata: [
                    'batch_size' => count($events),
                    'sdk_name' => 'langfuse-php',
                    'sdk_version' => '0.1.0',
                    'public_key' => $this->config->publicKey,
                ],
            ));
            $this->logErrors($response);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse flush error', ['message' => $throwable->getMessage()]);
        }
    }

    private function logErrors(?IngestionResponse $response): void
    {
        if ($response?->hasErrors() !== true) {
            return;
        }

        foreach ($response->errors as $error) {
            Log::warning('Langfuse ingestion event error', [
                'id' => $error->id,
                'status' => $error->status,
                'message' => $error->message,
            ]);
        }
    }

    public function count(): int
    {
        return count($this->queue);
    }
}
