<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Api;

use Axyr\Langfuse\Api\Concerns\SerializesQueryParameters;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\ObservationApiClientInterface;
use Axyr\Langfuse\Dto\ObservationListResponse;
use Axyr\Langfuse\Dto\ObservationQuery;
use Axyr\Langfuse\Dto\ObservationResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ObservationApiClient implements ObservationApiClientInterface
{
    use SerializesQueryParameters;

    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function get(string $observationId): ?ObservationResponse
    {
        try {
            return $this->doGet($observationId);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse observation fetch error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function getMany(?ObservationQuery $query = null): ?ObservationListResponse
    {
        try {
            return $this->doGetMany($query);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse observation list error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    private function doGet(string $observationId): ?ObservationResponse
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->observationsUrl($observationId));

        if (! $response->successful()) {
            Log::warning('Langfuse observation fetch failed', [
                'status' => $response->status(),
                'observationId' => $observationId,
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return ObservationResponse::fromArray($data);
    }

    private function doGetMany(?ObservationQuery $query): ?ObservationListResponse
    {
        $queryString = $this->buildQueryString($query?->toQuery() ?? []);

        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->observationsV2Url(), $queryString === '' ? [] : $queryString);

        if (! $response->successful()) {
            Log::warning('Langfuse observation list failed', [
                'status' => $response->status(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return ObservationListResponse::fromArray($data);
    }
}
