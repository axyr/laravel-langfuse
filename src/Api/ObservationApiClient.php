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

    /** Window used for a get-by-id that does not pass one; v4 tables are time-partitioned. */
    private const DEFAULT_LOOKBACK = '-30 days';

    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    /**
     * v2 has no get-by-id route: a single observation is an `id` filter on the
     * list. Pass the window you know the observation is in - without one this
     * falls back to the last 30 days.
     */
    public function get(
        string $observationId,
        ?string $fromStartTime = null,
        ?string $toStartTime = null,
        ?string $fields = null,
    ): ?ObservationResponse {
        $query = new ObservationQuery(
            fields: $fields,
            limit: 1,
            fromStartTime: $fromStartTime ?? $this->defaultFromStartTime(),
            toStartTime: $toStartTime,
            filter: ObservationQuery::idFilter($observationId),
        );

        $response = $this->getMany($query);

        return $response?->data[0] ?? null;
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

    private function defaultFromStartTime(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify(self::DEFAULT_LOOKBACK)
            ->format('Y-m-d\TH:i:s.u\Z');
    }
}
