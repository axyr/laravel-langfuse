<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Api;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\IngestionApiClientInterface;
use Axyr\Langfuse\Dto\IngestionBatch;
use Axyr\Langfuse\Dto\IngestionResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IngestionApiClient implements IngestionApiClientInterface
{
    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function send(IngestionBatch $batch): ?IngestionResponse
    {
        try {
            return $this->doSend($batch);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse ingestion error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    private function doSend(IngestionBatch $batch): ?IngestionResponse
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->post($this->config->ingestionUrl(), $batch->toArray());

        if (! $response->successful()) {
            Log::warning('Langfuse ingestion failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return IngestionResponse::fromArray($data);
    }
}
