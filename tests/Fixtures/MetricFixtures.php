<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Axyr\Langfuse\Dto\MetricQuery;

/**
 * Shared metrics read-API fixtures, modelled on the payloads in
 * docs/openapi/langfuse.yml (GET /api/public/v2/metrics). The v1 `traces` view
 * is gone; trace-level numbers come from the observations view filtered on
 * `isRootObservation`.
 */
class MetricFixtures
{
    /**
     * Count of traces grouped by trace name, expressed on the observations view.
     */
    public static function rootObservationsByTraceNameQuery(): MetricQuery
    {
        return new MetricQuery(
            view: 'observations',
            metrics: [['measure' => 'count', 'aggregation' => 'count']],
            fromTimestamp: '2024-05-01T00:00:00Z',
            toTimestamp: '2024-05-02T00:00:00Z',
            dimensions: [['field' => 'traceName']],
            filters: [[
                'column' => 'isRootObservation',
                'operator' => '=',
                'value' => 'true',
                'type' => 'boolean',
            ]],
        );
    }

    /**
     * The matching response: metric columns are named {aggregation}_{measure}.
     *
     * @return array<string, mixed>
     */
    public static function rootObservationsByTraceName(): array
    {
        return [
            'data' => [
                ['traceName' => 'chat', 'count_count' => 42],
                ['traceName' => 'summarize', 'count_count' => 17],
            ],
        ];
    }
}
