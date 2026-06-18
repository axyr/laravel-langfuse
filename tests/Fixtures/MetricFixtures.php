<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Shared metrics read-API fixtures, modelled on the payloads in
 * docs/openapi/langfuse.yml (GET /api/public/metrics).
 */
class MetricFixtures
{
    /**
     * A metrics response: count of traces grouped by name.
     *
     * @return array<string, mixed>
     */
    public static function tracesByName(): array
    {
        return [
            'data' => [
                ['name' => 'chat', 'count_count' => 42],
                ['name' => 'summarize', 'count_count' => 17],
            ],
        ];
    }
}
