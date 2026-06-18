<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ObservationQuery;

it('returns an empty query when no filters are set', function () {
    expect((new ObservationQuery())->toQuery())->toBe([]);
});

it('omits null filters and keeps the set ones', function () {
    $query = new ObservationQuery(
        fields: 'core,basic,usage',
        limit: 100,
        cursor: 'abc',
        type: 'GENERATION',
        traceId: 'trace-123',
        level: 'ERROR',
    );

    expect($query->toQuery())->toBe([
        'fields' => 'core,basic,usage',
        'limit' => 100,
        'cursor' => 'abc',
        'type' => 'GENERATION',
        'traceId' => 'trace-123',
        'level' => 'ERROR',
    ]);
});

it('keeps array environment filters', function () {
    $query = new ObservationQuery(
        environment: ['production', 'staging'],
        fromStartTime: '2024-05-01T00:00:00Z',
    );

    expect($query->toQuery())->toBe([
        'environment' => ['production', 'staging'],
        'fromStartTime' => '2024-05-01T00:00:00Z',
    ]);
});
