<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\DatasetItemQuery;

it('returns an empty query when no filters are set', function () {
    expect((new DatasetItemQuery())->toQuery())->toBe([]);
});

it('omits null filters and keeps the set ones', function () {
    $query = new DatasetItemQuery(
        datasetName: 'qa-eval',
        sourceTraceId: 'trace-1',
        page: 2,
        limit: 25,
    );

    expect($query->toQuery())->toBe([
        'datasetName' => 'qa-eval',
        'sourceTraceId' => 'trace-1',
        'page' => 2,
        'limit' => 25,
    ]);
});
