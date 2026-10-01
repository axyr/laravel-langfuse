<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Api;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\ExperimentApiClientInterface;
use Axyr\Langfuse\Dto\ExperimentItemListResponse;
use Axyr\Langfuse\Dto\ExperimentItemQuery;
use Axyr\Langfuse\Dto\ExperimentListResponse;
use Axyr\Langfuse\Dto\ExperimentQuery;
use Axyr\Langfuse\Dto\ExperimentResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads experiments back, replacing the deprecated dataset-run endpoints.
 * Requires Langfuse v4.
 */
class ExperimentApiClient implements ExperimentApiClientInterface
{
    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function listExperiments(ExperimentQuery $query): ?ExperimentListResponse
    {
        try {
            $data = $this->request($this->config->experimentsUrl(), $query->toQuery(), 'experiment list');

            return $data === null ? null : ExperimentListResponse::fromArray($data);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse experiment list error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function listExperimentItems(ExperimentItemQuery $query): ?ExperimentItemListResponse
    {
        try {
            $data = $this->request($this->config->experimentItemsUrl(), $query->toQuery(), 'experiment item list');

            return $data === null ? null : ExperimentItemListResponse::fromArray($data);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse experiment item list error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    /**
     * There is no get-by-id route: a single experiment is an `id` filter on the
     * list, and the API always needs a start-time window.
     */
    public function getExperiment(string $experimentId, string $fromStartTime, ?string $toStartTime = null): ?ExperimentResponse
    {
        $response = $this->listExperiments(new ExperimentQuery(
            fromStartTime: $fromStartTime,
            toStartTime: $toStartTime,
            id: [$experimentId],
            limit: 1,
        ));

        return $response?->data[0] ?? null;
    }

    /**
     * @param  array<string, string|int>  $parameters
     * @return array<string, mixed>|null
     */
    private function request(string $url, array $parameters, string $what): ?array
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($url, $parameters);

        if (! $response->successful()) {
            Log::warning('Langfuse ' . $what . ' failed', ['status' => $response->status()]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }
}
