<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Api;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScoreApiClient implements ScoreApiClientInterface
{
    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function delete(string $scoreId): bool
    {
        try {
            return $this->doDelete($scoreId);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse score delete error', ['message' => $throwable->getMessage()]);

            return false;
        }
    }

    private function doDelete(string $scoreId): bool
    {
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
