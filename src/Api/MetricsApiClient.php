<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Api;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\MetricsApiClientInterface;
use Axyr\Langfuse\Dto\MetricQuery;
use Axyr\Langfuse\Dto\MetricsResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetricsApiClient implements MetricsApiClientInterface
{
    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function query(MetricQuery $query): ?MetricsResponse
    {
        try {
            return $this->doQuery($query);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse metrics query error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    private function doQuery(MetricQuery $query): ?MetricsResponse
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->metricsV2Url(), [
                'query' => json_encode($query->toArray(), JSON_THROW_ON_ERROR),
            ]);

        if (! $response->successful()) {
            Log::warning('Langfuse metrics query failed', [
                'status' => $response->status(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return MetricsResponse::fromArray($data);
    }
}
