<?php

declare(strict_types=1);

namespace Langfuse\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Langfuse\Config\LangfuseConfig;
use Langfuse\Contracts\ScoreApiClientInterface;

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
