<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Api;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\DatasetRunApiClientInterface;
use Axyr\Langfuse\Dto\CreateDatasetRunItemBody;
use Axyr\Langfuse\Dto\DatasetRunItemListResponse;
use Axyr\Langfuse\Dto\DatasetRunItemResponse;
use Axyr\Langfuse\Dto\DatasetRunListResponse;
use Axyr\Langfuse\Dto\DatasetRunWithItemsResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DatasetRunApiClient implements DatasetRunApiClientInterface
{
    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function getRun(string $datasetName, string $runName): ?DatasetRunWithItemsResponse
    {
        try {
            return $this->doGetRun($datasetName, $runName);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse dataset run fetch error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function listRuns(string $datasetName, ?int $page = null, ?int $limit = null): ?DatasetRunListResponse
    {
        try {
            return $this->doListRuns($datasetName, $page, $limit);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse dataset run list error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function deleteRun(string $datasetName, string $runName): bool
    {
        try {
            return $this->doDeleteRun($datasetName, $runName);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse dataset run delete error', ['message' => $throwable->getMessage()]);

            return false;
        }
    }

    public function createRunItem(CreateDatasetRunItemBody $body): ?DatasetRunItemResponse
    {
        try {
            return $this->doCreateRunItem($body);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse dataset run item create error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function listRunItems(string $datasetId, string $runName, ?int $page = null, ?int $limit = null): ?DatasetRunItemListResponse
    {
        try {
            return $this->doListRunItems($datasetId, $runName, $page, $limit);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse dataset run item list error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    private function doGetRun(string $datasetName, string $runName): ?DatasetRunWithItemsResponse
    {
        $response = $this->authedRequest()->get($this->config->datasetRunsUrl($datasetName, $runName));

        if (! $response->successful()) {
            Log::warning('Langfuse dataset run fetch failed', [
                'status' => $response->status(),
                'datasetName' => $datasetName,
                'runName' => $runName,
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return DatasetRunWithItemsResponse::fromArray($data);
    }

    private function doListRuns(string $datasetName, ?int $page, ?int $limit): ?DatasetRunListResponse
    {
        $query = array_filter([
            'page' => $page,
            'limit' => $limit,
        ], fn(mixed $value): bool => $value !== null);

        $response = $this->authedRequest()->get($this->config->datasetRunsUrl($datasetName), $query);

        if (! $response->successful()) {
            Log::warning('Langfuse dataset run list failed', ['status' => $response->status()]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return DatasetRunListResponse::fromArray($data);
    }

    private function doDeleteRun(string $datasetName, string $runName): bool
    {
        $response = $this->authedRequest()->delete($this->config->datasetRunsUrl($datasetName, $runName));

        if (! $response->successful()) {
            Log::warning('Langfuse dataset run delete failed', [
                'status' => $response->status(),
                'datasetName' => $datasetName,
                'runName' => $runName,
            ]);

            return false;
        }

        return true;
    }

    private function doCreateRunItem(CreateDatasetRunItemBody $body): ?DatasetRunItemResponse
    {
        $response = $this->authedRequest()->post($this->config->datasetRunItemsUrl(), $body->toArray());

        if (! $response->successful()) {
            Log::warning('Langfuse dataset run item create failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return DatasetRunItemResponse::fromArray($data);
    }

    private function doListRunItems(string $datasetId, string $runName, ?int $page, ?int $limit): ?DatasetRunItemListResponse
    {
        $query = array_filter([
            'datasetId' => $datasetId,
            'runName' => $runName,
            'page' => $page,
            'limit' => $limit,
        ], fn(mixed $value): bool => $value !== null);

        $response = $this->authedRequest()->get($this->config->datasetRunItemsUrl(), $query);

        if (! $response->successful()) {
            Log::warning('Langfuse dataset run item list failed', ['status' => $response->status()]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return DatasetRunItemListResponse::fromArray($data);
    }

    private function authedRequest(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])->timeout($this->config->requestTimeout);
    }
}
