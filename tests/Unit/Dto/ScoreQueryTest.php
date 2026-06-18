<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ScoreQuery;

it('returns an empty query when no filters are set', function () {
    expect((new ScoreQuery())->toQuery())->toBe([]);
});

it('omits null filters and keeps the set ones', function () {
    $query = new ScoreQuery(
        page: 2,
        limit: 25,
        name: 'accuracy',
        datasetRunId: 'run-789',
        source: 'EVAL',
        dataType: 'NUMERIC',
    );

    expect($query->toQuery())->toBe([
        'page' => 2,
        'limit' => 25,
        'name' => 'accuracy',
        'source' => 'EVAL',
        'datasetRunId' => 'run-789',
        'dataType' => 'NUMERIC',
    ]);
});

it('keeps zero and array values', function () {
    $query = new ScoreQuery(
        value: 0.0,
        operator: '>=',
        environment: ['production', 'staging'],
        traceTags: ['v2'],
    );

    expect($query->toQuery())->toBe([
        'environment' => ['production', 'staging'],
        'operator' => '>=',
        'value' => 0.0,
        'traceTags' => ['v2'],
    ]);
});
