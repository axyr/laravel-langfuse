<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Jobs;

use Axyr\Langfuse\Contracts\IngestionApiClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendIngestionBatchJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {}

    public function handle(IngestionApiClientInterface $apiClient): void
    {
        $apiClient->sendRaw($this->payload);
    }
}
