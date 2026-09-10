<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\MetricQuery;

it('builds a minimal query, omitting empty optionals', function () {
    $query = new MetricQuery(
        view: 'observations',
        metrics: [['measure' => 'latency', 'aggregation' => 'p95']],
        fromTimestamp: '2024-05-01T00:00:00Z',
        toTimestamp: '2024-05-02T00:00:00Z',
    );

    expect($query->toArray())->toBe([
        'view' => 'observations',
        'metrics' => [['measure' => 'latency', 'aggregation' => 'p95']],
        'fromTimestamp' => '2024-05-01T00:00:00Z',
        'toTimestamp' => '2024-05-02T00:00:00Z',
    ]);
});

it('builds a full query with dimensions, time dimension and config', function () {
    $query = new MetricQuery(
        view: 'observations',
        metrics: [['measure' => 'count', 'aggregation' => 'count']],
        fromTimestamp: '2024-05-01T00:00:00Z',
        toTimestamp: '2024-05-02T00:00:00Z',
        dimensions: [['field' => 'name']],
        filters: [['column' => 'userId', 'operator' => '=', 'value' => 'u1', 'type' => 'string']],
        timeDimensionGranularity: 'day',
        orderBy: [['field' => 'count', 'direction' => 'desc']],
        configBins: 20,
        configRowLimit: 100,
    );

    expect($query->toArray())->toBe([
        'view' => 'observations',
        'metrics' => [['measure' => 'count', 'aggregation' => 'count']],
        'dimensions' => [['field' => 'name']],
        'filters' => [['column' => 'userId', 'operator' => '=', 'value' => 'u1', 'type' => 'string']],
        'fromTimestamp' => '2024-05-01T00:00:00Z',
        'toTimestamp' => '2024-05-02T00:00:00Z',
        'orderBy' => [['field' => 'count', 'direction' => 'desc']],
        'timeDimension' => ['granularity' => 'day'],
        'config' => ['bins' => 20, 'row_limit' => 100],
    ]);
});
