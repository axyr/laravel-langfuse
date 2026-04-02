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
        return $this->sendRaw($batch->toArray());
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function sendRaw(array $payload): ?IngestionResponse
    {
        try {
            return $this->doSend($payload);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse ingestion error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function doSend(array $payload): ?IngestionResponse
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->post($this->config->ingestionUrl(), $payload);

        if (! $response->successful()) {
            Log::warning('Langfuse ingestion failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        $result = IngestionResponse::fromArray($data);

        $this->logErrors($result);

        return $result;
    }

    private function logErrors(IngestionResponse $response): void
    {
        if (! $response->hasErrors()) {
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
}
