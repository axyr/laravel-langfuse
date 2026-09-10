<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ExperimentItemQuery;
use Axyr\Langfuse\Dto\ExperimentQuery;

it('always sends the required start-time window', function () {
    expect((new ExperimentQuery(fromStartTime: '2024-05-01T00:00:00Z'))->toQuery())
        ->toBe(['fromStartTime' => '2024-05-01T00:00:00Z']);
});

it('serialises experiment list filters as comma-separated values', function () {
    $query = new ExperimentQuery(
        fromStartTime: '2024-05-01T00:00:00Z',
        toStartTime: '2024-05-02T00:00:00Z',
        id: ['a', 'b'],
        name: ['nightly'],
        datasetId: ['ds-1', 'ds-2'],
        fields: 'core,scores',
        limit: 10,
        scoreLimit: 5,
        cursor: 'abc',
    );

    expect($query->toQuery())->toBe([
        'fromStartTime' => '2024-05-01T00:00:00Z',
        'toStartTime' => '2024-05-02T00:00:00Z',
        'id' => 'a,b',
        'name' => 'nightly',
        'datasetId' => 'ds-1,ds-2',
        'fields' => 'core,scores',
        'limit' => 10,
        'scoreLimit' => 5,
        'cursor' => 'abc',
    ]);
});

it('drops empty experiment list filters', function () {
    $query = new ExperimentQuery(fromStartTime: '2024-05-01T00:00:00Z', id: [], name: []);

    expect($query->toQuery())->toBe(['fromStartTime' => '2024-05-01T00:00:00Z']);
});

it('passes the structured filter through', function () {
    $query = new ExperimentQuery(
        fromStartTime: '2024-05-01T00:00:00Z',
        filter: '[{"column":"name","operator":"=","value":"nightly"}]',
    );

    expect($query->toQuery()['filter'])->toBe('[{"column":"name","operator":"=","value":"nightly"}]');
});

it('serialises experiment item filters as comma-separated values', function () {
    $query = new ExperimentItemQuery(
        fromStartTime: '2024-05-01T00:00:00Z',
        experimentId: ['exp-1', 'exp-2'],
        experimentName: ['nightly'],
        experimentItemId: ['item-1'],
        datasetId: ['ds-1'],
        fields: 'core,dataset,io',
        limit: 25,
    );

    expect($query->toQuery())->toBe([
        'fromStartTime' => '2024-05-01T00:00:00Z',
        'experimentId' => 'exp-1,exp-2',
        'experimentName' => 'nightly',
        'experimentItemId' => 'item-1',
        'datasetId' => 'ds-1',
        'fields' => 'core,dataset,io',
        'limit' => 25,
    ]);
});

it('drops empty experiment item filters', function () {
    expect((new ExperimentItemQuery(fromStartTime: '2024-05-01T00:00:00Z', experimentId: []))->toQuery())
        ->toBe(['fromStartTime' => '2024-05-01T00:00:00Z']);
});
