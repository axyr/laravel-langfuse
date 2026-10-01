<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Jobs;

use Axyr\Langfuse\Contracts\OtelTraceApiClientInterface;
use Axyr\Langfuse\Dto\OtelExportResult;
use Axyr\Langfuse\Exceptions\LangfuseExportRetryException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOtelBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /**
     * @param array<string, mixed> $payload
     * @param int $spanCount Recorded at dispatch so a failure can be logged without walking the payload.
     */
    public function __construct(
        public readonly array $payload,
        public readonly int $spanCount = 0,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(OtelTraceApiClientInterface $apiClient): void
    {
        $result = $apiClient->exportRaw($this->payload);

        // A partial success is already logged by the client and the OTLP spec
        // forbids retrying it.
        if ($result->accepted) {
            return;
        }

        if (! $result->retryable) {
            Log::warning('Langfuse OTLP batch dropped', [
                'status' => $result->status,
                'message' => $result->errorMessage,
            ]);

            return;
        }

        $this->retry($result);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('Langfuse OTLP batch failed permanently', [
            'message' => $exception->getMessage(),
            'spans' => $this->spanCount,
        ]);
    }

    private function retry(OtelExportResult $result): void
    {
        if ($result->retryAfterSeconds !== null) {
            $this->release($result->retryAfterSeconds);

            return;
        }

        throw LangfuseExportRetryException::forStatus($result->status, $result->errorMessage);
    }
}
