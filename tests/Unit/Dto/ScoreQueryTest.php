<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ScoreQuery;

it('returns an empty query when no filters are set', function () {
    expect((new ScoreQuery())->toQuery())->toBe([]);
});

it('omits null filters and keeps the set ones', function () {
    $query = new ScoreQuery(
        limit: 25,
        cursor: 'abc',
        name: ['accuracy'],
        source: ['EVAL'],
        dataType: ['NUMERIC'],
        experimentId: ['run-789'],
    );

    expect($query->toQuery())->toBe([
        'limit' => 25,
        'cursor' => 'abc',
        'name' => 'accuracy',
        'source' => 'EVAL',
        'dataType' => 'NUMERIC',
        'experimentId' => 'run-789',
    ]);
});

it('serialises list filters as comma-separated values', function () {
    $query = new ScoreQuery(
        id: ['a', 'b'],
        environment: ['production', 'staging'],
        traceId: ['t1', 't2'],
    );

    expect($query->toQuery())->toBe([
        'id' => 'a,b',
        'environment' => 'production,staging',
        'traceId' => 't1,t2',
    ]);
});

it('drops empty list filters', function () {
    expect((new ScoreQuery(id: [], environment: []))->toQuery())->toBe([]);
});

it('keeps numeric bounds including zero', function () {
    $query = new ScoreQuery(value: [0, 1], valueMin: 0.0, valueMax: 1.0, dataType: ['NUMERIC']);

    expect($query->toQuery())->toBe([
        'dataType' => 'NUMERIC',
        'value' => '0,1',
        'valueMin' => 0.0,
        'valueMax' => 1.0,
    ]);
});

it('passes the timestamp window through', function () {
    $query = new ScoreQuery(fromTimestamp: '2024-05-01T00:00:00Z', toTimestamp: '2024-05-02T00:00:00Z');

    expect($query->toQuery())->toBe([
        'fromTimestamp' => '2024-05-01T00:00:00Z',
        'toTimestamp' => '2024-05-02T00:00:00Z',
    ]);
});
