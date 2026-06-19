<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Api;

use Axyr\Langfuse\Api\Concerns\SerializesQueryParameters;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Dto\ScoreListResponse;
use Axyr\Langfuse\Dto\ScoreQuery;
use Axyr\Langfuse\Dto\ScoreResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScoreApiClient implements ScoreApiClientInterface
{
    use SerializesQueryParameters;

    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function get(string $scoreId): ?ScoreResponse
    {
        try {
            return $this->doGet($scoreId);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse score fetch error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function getMany(?ScoreQuery $query = null): ?ScoreListResponse
    {
        try {
            return $this->doGetMany($query);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse score list error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function delete(string $scoreId): bool
    {
        try {
            return $this->doDelete($scoreId);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse score delete error', ['message' => $throwable->getMessage()]);

            return false;
        }
    }

    private function doGet(string $scoreId): ?ScoreResponse
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->scoresV2Url($scoreId));

        if (! $response->successful()) {
            Log::warning('Langfuse score fetch failed', [
                'status' => $response->status(),
                'scoreId' => $scoreId,
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return ScoreResponse::fromArray($data);
    }

    private function doGetMany(?ScoreQuery $query): ?ScoreListResponse
    {
        $queryString = $this->buildQueryString($query?->toQuery() ?? []);

        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->scoresV2Url(), $queryString === '' ? [] : $queryString);

        if (! $response->successful()) {
            Log::warning('Langfuse score list failed', [
                'status' => $response->status(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return ScoreListResponse::fromArray($data);
    }

    private function doDelete(string $scoreId): bool
    {
        // Intentional: reads (get/getMany) use the v2 scores endpoint, but the
        // spec only exposes DELETE on the v1 endpoint (scoresUrl, not
        // scoresV2Url). Do not "consolidate" this onto v2 - there is no v2 DELETE.
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
        ])
            ->timeout($this->config->requestTimeout)
            ->delete($this->config->scoresUrl($scoreId));

        if (! $response->successful()) {
            Log::warning('Langfuse score delete failed', [
                'status' => $response->status(),
                'scoreId' => $scoreId,
            ]);

            return false;
        }

        return true;
    }
}
