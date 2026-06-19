<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Api;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\DatasetItemApiClientInterface;
use Axyr\Langfuse\Dto\CreateDatasetItemBody;
use Axyr\Langfuse\Dto\DatasetItemListResponse;
use Axyr\Langfuse\Dto\DatasetItemQuery;
use Axyr\Langfuse\Dto\DatasetItemResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DatasetItemApiClient implements DatasetItemApiClientInterface
{
    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function get(string $id): ?DatasetItemResponse
    {
        try {
            return $this->doGet($id);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse dataset item fetch error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function list(?DatasetItemQuery $query = null): ?DatasetItemListResponse
    {
        try {
            return $this->doList($query);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse dataset item list error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function create(CreateDatasetItemBody $body): ?DatasetItemResponse
    {
        try {
            return $this->doCreate($body);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse dataset item create error', ['message' => $throwable->getMessage()]);

            return null;
        }
    }

    public function delete(string $id): bool
    {
        try {
            return $this->doDelete($id);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse dataset item delete error', ['message' => $throwable->getMessage()]);

            return false;
        }
    }

    private function doGet(string $id): ?DatasetItemResponse
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->datasetItemsUrl($id));

        if (! $response->successful()) {
            Log::warning('Langfuse dataset item fetch failed', [
                'status' => $response->status(),
                'id' => $id,
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return DatasetItemResponse::fromArray($data);
    }

    private function doList(?DatasetItemQuery $query): ?DatasetItemListResponse
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->get($this->config->datasetItemsUrl(), $query?->toQuery() ?? []);

        if (! $response->successful()) {
            Log::warning('Langfuse dataset item list failed', [
                'status' => $response->status(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return DatasetItemListResponse::fromArray($data);
    }

    private function doCreate(CreateDatasetItemBody $body): ?DatasetItemResponse
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->config->requestTimeout)
            ->post($this->config->datasetItemsUrl(), $body->toArray());

        if (! $response->successful()) {
            Log::warning('Langfuse dataset item create failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return DatasetItemResponse::fromArray($data);
    }

    private function doDelete(string $id): bool
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->authHeader(),
        ])
            ->timeout($this->config->requestTimeout)
            ->delete($this->config->datasetItemsUrl($id));

        if (! $response->successful()) {
            Log::warning('Langfuse dataset item delete failed', [
                'status' => $response->status(),
                'id' => $id,
            ]);

            return false;
        }

        return true;
    }
}
